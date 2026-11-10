<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Amazon SP-APIから出品商品を取得し、amazon_product_masterへ保存するサービス。
 */
class AmazonProductMasterService
{
    public function __construct(private AmazonOrderService $amazon)
    {
    }

    /**
     * Amazonの商品情報を全ページ取得し、購入可能または検索可能なSKUだけを同期する。
     * 価格・SKU・在庫の情報はSKUごとに保存し、画像は公開ストレージにも保存する。
     *
     * @return array{fetched: int, saved: int} 取得件数と保存件数
     */
    public function sync(): array
    {
        $listings = $this->amazon->fetchAllListings();
        $activeListings = [];
        $inactiveSkus = [];

        foreach ($listings as $listing) {
            $sellerSku = (string) ($listing['sku'] ?? '');
            if ($sellerSku === '') {
                continue;
            }
            if ($this->isActiveListing($listing)) {
                $activeListings[$sellerSku] = $listing;
            } else {
                $inactiveSkus[] = $sellerSku;
            }
        }

        $shippingTemplateNames = [];
        $productTypes = array_values(array_unique(array_filter(array_map(
            static fn (array $listing): ?string => $listing['summaries'][0]['productType'] ?? null,
            array_values($activeListings)
        ))));
        foreach ($productTypes as $productType) {
            try {
                $shippingTemplateNames += $this->amazon->fetchMerchantShippingTemplateNames($productType);
            } catch (\Throwable $exception) {
                Log::warning('Amazon配送テンプレート名の取得に失敗しました。', [
                    'product_type' => $productType,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        $inventoryBySku = [];
        if ($activeListings !== []) {
            foreach ($this->amazon->fetchInventorySummaries(array_keys($activeListings)) as $summary) {
                $sellerSku = (string) ($summary['sellerSku'] ?? '');
                $quantity = $summary['inventoryDetails']['fulfillableQuantity'] ?? null;
                if ($sellerSku !== '' && is_numeric($quantity)) {
                    $inventoryBySku[$sellerSku] = max(0, (int) $quantity);
                }
            }
        }

        $imagePaths = [];
        foreach ($activeListings as $sellerSku => $listing) {
            $imageUrl = $listing['summaries'][0]['mainImage']['link'] ?? null;
            if (is_string($imageUrl) && $imageUrl !== '') {
                $path = $this->downloadProductImage($sellerSku, $imageUrl);
                if ($path !== null) {
                    $imagePaths[$sellerSku] = Storage::url($path);
                }
            }
        }

        $saved = 0;
        DB::transaction(function () use ($activeListings, $inactiveSkus, $inventoryBySku, $imagePaths, $shippingTemplateNames, &$saved): void {
            if ($inactiveSkus !== []) {
                $rows = DB::table('amazon_product_master')
                    ->whereIn('seller_sku', $inactiveSkus)
                    ->get(['seller_sku', 'image_path']);
                foreach ($rows as $row) {
                    $path = str_replace('/storage/', '', (string) $row->image_path);
                    if (str_starts_with($path, 'product_image/amazon_')) {
                        Storage::disk('public')->delete($path);
                    }
                }
                DB::table('amazon_product_master')->whereIn('seller_sku', $inactiveSkus)->delete();
            }

            foreach ($activeListings as $sellerSku => $listing) {
                $summary = $listing['summaries'][0] ?? [];
                $price = $this->extractPrice($listing);
                $inventoryQuantity = $inventoryBySku[$sellerSku] ?? $this->extractListingQuantity($listing);
                $shippingSize = $this->extractShippingSize($listing, $shippingTemplateNames);
                $values = [
                    'asin' => $summary['asin'] ?? null,
                    'parent_sku' => $this->extractParentSku($listing),
                    'product_name' => $summary['itemName'] ?? null,
                    'status' => implode(',', is_array($summary['status'] ?? null) ? $summary['status'] : []),
                    'sale_price' => $price['amount'],
                    'currency' => $price['currency'],
                    'total_stock' => $inventoryQuantity,
                    'shipping_size' => $shippingSize,
                    'sku_data' => json_encode($this->formatSku($sellerSku, $listing, $price, $inventoryQuantity, $shippingSize), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'raw_data' => json_encode($listing, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ];
                if (isset($imagePaths[$sellerSku])) {
                    $values['image_path'] = $imagePaths[$sellerSku];
                }

                $exists = DB::table('amazon_product_master')->where('seller_sku', $sellerSku)->exists();
                if ($exists) {
                    DB::table('amazon_product_master')->where('seller_sku', $sellerSku)->update($values);
                } else {
                    DB::table('amazon_product_master')->insert($values + [
                        'seller_sku' => $sellerSku,
                        'created_at' => now(),
                    ]);
                }
                $saved++;
            }
        });

        return ['fetched' => count($activeListings), 'saved' => $saved];
    }

    /**
     * 出品が購入可能またはAmazon上で検索可能かを判定する。
     *
     * @param array<string, mixed> $listing Amazon出品情報
     */
    private function isActiveListing(array $listing): bool
    {
        $statuses = $listing['summaries'][0]['status'] ?? [];

        return is_array($statuses)
            && (in_array('BUYABLE', $statuses, true) || in_array('DISCOVERABLE', $statuses, true));
    }

    /**
     * Amazon出品情報から販売価格と通貨を取り出す。
     *
     * @param array<string, mixed> $listing Amazon出品情報
     * @return array{amount: string|null, currency: string|null}
     */
    private function extractPrice(array $listing): array
    {
        $offer = $listing['offers'][0] ?? [];
        $price = $offer['price'] ?? [];

        return [
            'amount' => isset($price['amount']) ? (string) $price['amount'] : null,
            'currency' => $price['currencyCode'] ?? null,
        ];
    }

    /**
     * バリエーション関係データから親SKUを取得する。
     *
     * @param array<string, mixed> $listing Amazon出品情報
     */
    private function extractParentSku(array $listing): ?string
    {
        foreach (($listing['relationships'] ?? []) as $marketplaceRelationship) {
            foreach (($marketplaceRelationship['relationships'] ?? []) as $relationship) {
                $parentSku = $relationship['parentSkus'][0] ?? null;
                if (is_string($parentSku) && $parentSku !== '') {
                    return $parentSku;
                }
            }
        }

        foreach (($listing['attributes']['child_parent_sku_relationship'] ?? []) as $relationship) {
            $parentSku = $relationship['parent_sku'] ?? null;
            if (is_string($parentSku) && $parentSku !== '') {
                return $parentSku;
            }
        }

        return null;
    }

    /**
     * 配送パターン名に含まれる対応サイズを数値で取得する。
     *
     * @param array<string, mixed> $listing Amazon出品情報
     */
    private function extractShippingSize(array $listing, array $shippingTemplateNames): ?int
    {
        $attributes = $listing['attributes'] ?? [];
        $configuredTemplateIds = [];
        foreach (($attributes['merchant_shipping_group'] ?? []) as $template) {
            if (is_array($template) && is_string($template['value'] ?? null)) {
                $configuredTemplateIds[] = $template['value'];
            }
        }

        $candidates = [];
        foreach ($configuredTemplateIds as $templateId) {
            $candidates[] = $shippingTemplateNames[$templateId] ?? $templateId;
        }
        foreach (['merchant_shipping_group', 'merchant_shipping_group_name', 'shipping_pattern'] as $key) {
            $values = $attributes[$key] ?? [];
            foreach (is_array($values) ? $values : [] as $attribute) {
                $value = is_array($attribute) ? ($attribute['value'] ?? $attribute['name'] ?? '') : $attribute;
                if (!is_scalar($value)) {
                    continue;
                }
                $candidates[] = (string) $value;
            }
        }
        foreach ($candidates as $candidate) {
            if (preg_match('/(60|80|100|120|140|160|180|200)\s*サイズ/u', $candidate, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        if ($configuredTemplateIds !== []) {
            Log::warning('Amazon配送テンプレート名から配送サイズを判定できませんでした。', [
                'seller_sku' => $listing['sku'] ?? null,
                'template_ids' => $configuredTemplateIds,
                'resolved_names' => array_intersect_key($shippingTemplateNames, array_flip($configuredTemplateIds)),
            ]);
        }

        return null;
    }

    /**
     * FBA在庫が取得できない場合に、出品情報に含まれる在庫数を合算する。
     *
     * @param array<string, mixed> $listing Amazon出品情報
     */
    private function extractListingQuantity(array $listing): int
    {
        $total = 0;
        foreach (($listing['fulfillmentAvailability'] ?? []) as $availability) {
            $total += (int) ($availability['quantity'] ?? 0);
        }

        return max(0, $total);
    }

    /**
     * 出品SKUの識別情報、バリエーション、価格、在庫をJSON用に整形する。
     *
     * @param array<string, mixed> $listing Amazon出品情報
     * @param array{amount: string|null, currency: string|null} $price 販売価格
     * @return array<string, mixed>
     */
    private function formatSku(string $sellerSku, array $listing, array $price, int $stock, ?int $shippingSize): array
    {
        $attributes = $listing['attributes'] ?? [];
        $variationNames = [];
        foreach (['size_name', 'color_name', 'style_name', 'pattern_name', 'flavor', 'scent'] as $key) {
            if (!isset($attributes[$key]) || !is_array($attributes[$key])) {
                continue;
            }
            $value = $attributes[$key][0]['value'] ?? $attributes[$key][0]['language_tag'] ?? null;
            if (is_scalar($value) && (string) $value !== '') {
                $variationNames[] = $key . ': ' . (string) $value;
            }
        }

        return [
            'seller_sku' => $sellerSku,
            'parent_sku' => $this->extractParentSku($listing),
            'asin' => $listing['summaries'][0]['asin'] ?? null,
            'variation_name' => implode(' / ', $variationNames),
            'sale_price' => $price['amount'],
            'currency' => $price['currency'],
            'stock' => $stock,
            'shipping_size' => $shippingSize,
        ];
    }

    /**
     * Amazon商品画像を検証して公開ストレージへ保存する。
     *
     * @param string $sellerSku Amazon出品SKU
     * @param string $imageUrl Amazon APIが返した画像URL
     * @return string|null 保存先の相対パス。取得に失敗した場合はnull
     */
    private function downloadProductImage(string $sellerSku, string $imageUrl): ?string
    {
        $host = parse_url($imageUrl, PHP_URL_HOST);
        $allowedHosts = ['media-amazon.com', 'images-amazon.com', 'ssl-images-amazon.com'];
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

            $path = 'product_image/amazon_' . hash('sha256', $sellerSku) . '.' . $extension;
            Storage::disk('public')->put($path, $response->body());

            return $path;
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
