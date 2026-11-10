<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * TikTok Shopの商品情報を取得し、商品マスタテーブルへ保存するサービス。
 */
class TikTokProductMasterService
{
    public function __construct(private TikTokInventoryService $inventory)
    {
    }

    /**
     * TikTok Shopの商品を全件取得し、商品IDをキーに新規登録または更新する。
     * APIの全項目は raw_data にJSON形式で保存する。
     *
     * @return array{fetched: int, saved: int} 取得件数と保存件数
     */
    public function sync(): array
    {
        $products = $this->inventory->fetchAllProducts();
        $inactiveProductIds = [];
        $products = array_values(array_filter($products, static function (array $product) use (&$inactiveProductIds): bool {
            $productId = (string) ($product['id'] ?? '');
            $status = strtoupper((string) ($product['status'] ?? $product['product_status'] ?? ''));
            if ($productId !== '' && $status !== 'ACTIVATE') {
                $inactiveProductIds[] = $productId;
            }

            return $status === 'ACTIVATE';
        }));
        $saved = 0;
        $localImagePaths = [];

        foreach ($products as $product) {
            $productId = (string) ($product['id'] ?? '');
            $imageUrl = $this->extractImageUrls($product)[0] ?? null;
            if ($productId !== '' && $imageUrl !== null) {
                $path = $this->downloadProductImage($productId, $imageUrl);
                if ($path !== null) {
                    $localImagePaths[$productId] = Storage::url($path);
                }
            }
        }

        DB::transaction(function () use ($products, $localImagePaths, $inactiveProductIds, &$saved): void {
            if ($inactiveProductIds !== []) {
                $inactiveRows = DB::table('tiktok_product_master')
                    ->whereIn('product_id', $inactiveProductIds)
                    ->get(['product_id', 'image_path']);
                foreach ($inactiveRows as $row) {
                    $path = str_replace('/storage/', '', (string) $row->image_path);
                    if (str_starts_with($path, 'product_image/tiktok_')) {
                        Storage::disk('public')->delete($path);
                    }
                }
                DB::table('tiktok_product_master')->whereIn('product_id', $inactiveProductIds)->delete();
            }

            foreach ($products as $product) {
                $productId = (string) ($product['id'] ?? '');
                if ($productId === '') {
                    continue;
                }

                $values = [
                    'product_name' => $product['title'] ?? null,
                    'status' => $product['status'] ?? null,
                    'total_stock' => $this->calculateTotalStock($product['skus'] ?? []),
                    'sale_price' => $this->getCommonSalePrice($product['skus'] ?? []),
                    'sku_data' => json_encode($this->formatSkus($product['skus'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'raw_data' => json_encode($product, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ];
                if (isset($localImagePaths[$productId])) {
                    $values['image_path'] = $localImagePaths[$productId];
                }
                $existing = DB::table('tiktok_product_master')->where('product_id', $productId)->exists();
                if ($existing) {
                    DB::table('tiktok_product_master')->where('product_id', $productId)->update($values);
                } else {
                    DB::table('tiktok_product_master')->insert($values + [
                        'product_id' => $productId,
                        'created_at' => now(),
                    ]);
                }

                $saved++;
            }
        });

        return ['fetched' => count($products), 'saved' => $saved];
    }

    /**
     * 商品画像情報からURLまたはTikTok Shopの画像URIを抽出する。
     *
     * @param array<string, mixed> $product 商品詳細
     * @return array<int, string> 商品画像のURLまたはURI一覧
     */
    private function extractImageUrls(array $product): array
    {
        $images = $product['main_images'] ?? $product['product_main_image'] ?? [];
        if (is_string($images)) {
            return [$images];
        }
        if (!is_array($images)) {
            return [];
        }

        $urls = [];
        foreach ($images as $image) {
            if (is_string($image) && $image !== '') {
                $urls[] = $image;
            } elseif (is_array($image)) {
                $url = $image['urls'][0] ?? $image['url'] ?? $image['uri'] ?? null;
                if (is_string($url) && $url !== '') {
                    $urls[] = $url;
                }
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * TikTokの商品画像を公開ストレージへ保存する。
     *
     * @param string $productId TikTok Shop商品ID
     * @param string $imageUrl TikTok APIから返された画像URL
     * @return string|null 保存先の相対パス。取得失敗時はnull
     */
    private function downloadProductImage(string $productId, string $imageUrl): ?string
    {
        $host = parse_url($imageUrl, PHP_URL_HOST);
        $allowedHosts = ['ibyteimg.com', 'tiktokcdn.com', 'byteoversea.com'];
        $isAllowedHost = is_string($host) && collect($allowedHosts)->contains(
            static fn (string $domain): bool => $host === $domain || str_ends_with($host, '.' . $domain)
        );
        if (parse_url($imageUrl, PHP_URL_SCHEME) !== 'https' || !$isAllowedHost) {
            return null;
        }

        try {
            $response = Http::timeout(20)->get($imageUrl);
            if (!$response->successful()) {
                return null;
            }

            $mimeType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
            $extension = match ($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
                default => null,
            };
            if ($extension === null || strlen($response->body()) > 10 * 1024 * 1024) {
                return null;
            }

            $path = 'product_image/tiktok_' . hash('sha256', $productId) . '.' . $extension;
            Storage::disk('public')->put($path, $response->body());

            return $path;
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * 全SKU・全倉庫の在庫数を合算する。
     *
     * @param mixed $skus TikTok APIが返すSKU一覧
     */
    private function calculateTotalStock(mixed $skus): int
    {
        if (!is_array($skus)) {
            return 0;
        }

        $total = 0;
        foreach ($skus as $sku) {
            foreach (($sku['inventory'] ?? []) as $warehouse) {
                $total += (int) ($warehouse['quantity'] ?? 0);
            }
        }

        return $total;
    }

    /**
     * すべてのSKUで同一価格の場合のみ、商品共通価格として返す。
     * SKUごとに価格が異なる場合の価格は sku_data に保存する。
     *
     * @param mixed $skus TikTok APIが返すSKU一覧
     */
    private function getCommonSalePrice(mixed $skus): ?string
    {
        if (!is_array($skus) || $skus === []) {
            return null;
        }

        $prices = [];
        foreach ($skus as $sku) {
            $price = $sku['price']['sale_price'] ?? null;
            if ($price === null) {
                return null;
            }
            $prices[] = (string) $price;
        }

        return count(array_unique($prices)) === 1 ? $prices[0] : null;
    }

    /**
     * SKU ID、バリエーション名、価格、在庫を保存用データに整形する。
     *
     * @param mixed $skus TikTok APIが返すSKU一覧
     * @return array<int, array<string, mixed>>
     */
    private function formatSkus(mixed $skus): array
    {
        if (!is_array($skus)) {
            return [];
        }

        $formatted = [];
        foreach ($skus as $sku) {
            $attributes = $sku['sales_attributes'] ?? [];
            $variationNames = [];
            foreach (is_array($attributes) ? $attributes : [] as $attribute) {
                $name = $attribute['name'] ?? '';
                $value = $attribute['value_name'] ?? $attribute['value'] ?? '';
                if ($name !== '' || $value !== '') {
                    $variationNames[] = trim($name . ($name !== '' && $value !== '' ? ': ' : '') . $value);
                }
            }

            $formatted[] = [
                'sku_id' => isset($sku['id']) ? (string) $sku['id'] : null,
                'seller_sku' => $sku['seller_sku'] ?? null,
                'variation_name' => implode(' / ', $variationNames),
                'sale_price' => $sku['price']['sale_price'] ?? null,
                'currency' => $sku['price']['currency'] ?? null,
                'stock' => array_sum(array_map(
                    static fn (array $warehouse): int => (int) ($warehouse['quantity'] ?? 0),
                    is_array($sku['inventory'] ?? null) ? $sku['inventory'] : []
                )),
            ];
        }

        return $formatted;
    }
}
