<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * TikTok Shopの商品一覧からSKU在庫数を取得し、商品マスタへ反映するサービス。
 */
class TikTokInventoryService
{
    private const API_PATH = '/product/202309/products/search';

    /**
     * 注文同期サービスのトークン更新処理を共有する。
     */
    public function __construct(private TikTokOrderService $orders)
    {
    }

    /**
     * TikTokの商品を全ページ取得し、SKUの在庫情報を返す。
     *
     * @return array<int, array<string, mixed>> 商品検索APIが返した商品情報
     */
    public function fetchInventory(): array
    {
        $tokenRow = DB::table('tiktok_tokens')->latest('updated_at')->first();
        if (!$tokenRow || empty($tokenRow->access_token)) {
            throw new RuntimeException('TikTokのアクセストークンがありません。先に /tiktok/auth で認証してください。');
        }

        $tokenRow = $this->orders->ensureValidAccessToken($tokenRow);
        $pageToken = null;
        $products = [];

        do {
            $data = $this->searchProducts($tokenRow, $pageToken);
            $pageProducts = $data['products'] ?? [];
            if (!is_array($pageProducts)) {
                throw new RuntimeException('TikTokの商品一覧レスポンスの形式が正しくありません。');
            }

            foreach ($pageProducts as $product) {
                if (!empty($product['id'])) {
                    $products[(string) $product['id']] = $product;
                }
            }

            $pageToken = $data['next_page_token'] ?? null;
        } while (!empty($pageToken));

        // 商品検索APIにはSKU IDは含まれるが、バリエーション名が含まれないため詳細APIで補う。
        $detailedProducts = [];
        foreach ($products as $productId => $searchProduct) {
            $product = $this->getProductDetails($tokenRow, $productId);
            if (strtoupper((string) ($product['status'] ?? $searchProduct['status'] ?? '')) !== 'ACTIVATE') {
                continue;
            }

            $detailedProducts[] = array_merge($searchProduct, $product);
        }

        Log::info('TikTok在庫情報を取得しました。', ['products' => count($detailedProducts)]);

        return $detailedProducts;
    }

    /**
     * TikTokの在庫数を商品マスタへ反映し、関連するセット在庫を再計算する。
     *
     * セットSKUごとの在庫数と使用数量から商品在庫候補を算出し、複数セットが
     * 同じ商品を使う場合は最も少ない候補を採用して過剰販売を防ぐ。
     *
     * @return array{products: array<int, array<string, mixed>>, updated_products: int, updated_variations: int}
     */
    public function syncInventory(): array
    {
        $products = $this->fetchInventory();
        $skuIds = [];
        foreach ($products as $product) {
            foreach (($product['skus'] ?? []) as $sku) {
                if (!empty($sku['id'])) {
                    $skuIds[] = (string) $sku['id'];
                }
            }
        }

        $variations = DB::table('sales_variations')
            ->whereIn('sku_id', array_values(array_unique($skuIds)))
            ->get(['id', 'sku_id'])
            ->keyBy('sku_id');
        $variationProducts = DB::table('sales_variation_products')
            ->whereIn('sales_variation_id', $variations->pluck('id')->all())
            ->get(['id', 'sales_variation_id', 'product_management_code', 'quantity']);
        $productCodes = $variationProducts->pluck('product_management_code')
            ->merge(collect($products)->pluck('id'))
            ->filter()
            ->map(static fn ($code) => (string) $code)
            ->unique()
            ->values();
        $masterCodes = DB::table('product_master')
            ->whereNull('deleted_at')
            ->whereIn('product_management_code', $productCodes)
            ->pluck('product_management_code')
            ->map(static fn ($code) => (string) $code)
            ->flip();

        $stockCandidates = [];
        $singleProductStocks = [];
        foreach ($products as $product) {
            $productId = (string) ($product['id'] ?? '');
            foreach (($product['skus'] ?? []) as $sku) {
                $skuStock = $this->getAvailableQuantity($sku['inventory'] ?? []);
                if ($skuStock === null) {
                    continue;
                }

                $variation = $variations->get((string) ($sku['id'] ?? ''));
                if ($variation) {
                    foreach ($variationProducts->where('sales_variation_id', $variation->id) as $item) {
                        $code = (string) $item->product_management_code;
                        if ($code === $productId && $masterCodes->has($code)) {
                            $stockCandidates[$code][] = $skuStock * max(1, (int) $item->quantity);
                        }
                    }
                } elseif ($masterCodes->has($productId)) {
                    $singleProductStocks[$productId] = $skuStock;
                }
            }
        }

        $targetStocks = $singleProductStocks;
        foreach ($stockCandidates as $code => $candidates) {
            $targetStocks[$code] = min($candidates);
        }

        $updatedProducts = 0;
        $updatedVariations = 0;
        DB::transaction(function () use ($targetStocks, &$updatedProducts, &$updatedVariations): void {
            foreach ($targetStocks as $productCode => $stock) {
                // 数字だけのコードはPHP配列のキーになると整数化するため、DB比較前に文字列へ戻す。
                $productCode = (string) $productCode;
                $updatedProducts += DB::table('product_master')
                    ->whereRaw('CAST(product_management_code AS CHAR) = ?', [$productCode])
                    ->whereNull('deleted_at')
                    ->update(['stock' => $stock, 'updated_at' => now()]);

                $linkedVariations = DB::table('sales_variation_products')
                    ->whereRaw('CAST(product_management_code AS CHAR) = ?', [$productCode])
                    ->get(['id', 'quantity']);
                foreach ($linkedVariations as $linkedVariation) {
                    $usageQuantity = max(1, (int) $linkedVariation->quantity);
                    $variationStock = intdiv((int) $stock, $usageQuantity);
                    $updatedVariations += DB::table('sales_variation_products')
                        ->where('id', $linkedVariation->id)
                        ->update(['variation_stock' => $variationStock, 'updated_at' => now()]);
                }
            }
        });

        return [
            'products' => $products,
            'updated_products' => $updatedProducts,
            'updated_variations' => $updatedVariations,
        ];
    }

    /**
     * 商品検索APIを1ページ呼び出し、署名検証済みのレスポンスデータを返す。
     *
     * @param object $tokenRow 有効なショップ認証情報
     * @param string|null $pageToken 次ページ取得用トークン
     * @return array<string, mixed>
     */
    private function searchProducts(object $tokenRow, ?string $pageToken): array
    {
        $appKey = config('services.tiktok.app_key');
        $appSecret = config('services.tiktok.app_secret');
        $query = [
            'app_key' => $appKey,
            'shop_cipher' => $tokenRow->shop_cipher,
            'timestamp' => time(),
            'page_size' => 100,
        ];
        if ($pageToken !== null) {
            $query['page_token'] = $pageToken;
        }

        ksort($query);
        $parameterString = '';
        foreach ($query as $key => $value) {
            $parameterString .= $key . $value;
        }
        // 稼働中商品のみ検索することで、削除・停止済み商品を一覧から除外する。
        $body = json_encode(['status' => 'ACTIVATE'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $appSecret . self::API_PATH . $parameterString . $body . $appSecret, $appSecret);
        $query['sign'] = $signature;

        $response = Http::withHeaders([
            'x-tts-access-token' => $tokenRow->access_token,
            'Content-Type' => 'application/json',
        ])->withBody($body, 'application/json')->post(
            'https://open-api.tiktokglobalshop.com' . self::API_PATH . '?' . http_build_query($query)
        );

        $responseData = $response->json();
        if (!$response->successful() || !is_array($responseData) || (int) ($responseData['code'] ?? -1) !== 0) {
            Log::error('TikTok商品検索APIに失敗しました。', ['response' => $responseData]);
            throw new RuntimeException('TikTok商品検索APIに失敗しました: ' . ($responseData['message'] ?? $response->status()));
        }

        return $responseData['data'] ?? [];
    }

    /**
     * 商品詳細APIからSKU IDとバリエーション属性を含む商品情報を取得する。
     *
     * @param object $tokenRow 有効なショップ認証情報
     * @param string $productId TikTok Shopの商品ID
     * @return array<string, mixed> 商品詳細
     */
    private function getProductDetails(object $tokenRow, string $productId): array
    {
        $path = '/product/202309/products/' . $productId;
        $query = [
            'app_key' => config('services.tiktok.app_key'),
            'shop_cipher' => $tokenRow->shop_cipher,
            'timestamp' => time(),
        ];

        ksort($query);
        $parameterString = '';
        foreach ($query as $key => $value) {
            $parameterString .= $key . $value;
        }
        $query['sign'] = hash_hmac(
            'sha256',
            config('services.tiktok.app_secret') . $path . $parameterString . config('services.tiktok.app_secret'),
            config('services.tiktok.app_secret')
        );

        $response = Http::withHeaders([
            'x-tts-access-token' => $tokenRow->access_token,
            'Content-Type' => 'application/json',
        ])->get('https://open-api.tiktokglobalshop.com' . $path, $query);

        $responseData = $response->json();
        if (!$response->successful() || !is_array($responseData) || (int) ($responseData['code'] ?? -1) !== 0) {
            Log::error('TikTok商品詳細APIに失敗しました。', [
                'product_id' => $productId,
                'response' => $responseData,
            ]);
            throw new RuntimeException('TikTok商品詳細APIに失敗しました: ' . ($responseData['message'] ?? $response->status()));
        }

        $details = $responseData['data'] ?? [];
        return $details['product'] ?? $details;
    }

    /**
     * SKUの倉庫別在庫を合算して返す。
     *
     * @param array<int, array<string, mixed>> $inventory 倉庫ごとの在庫情報
     */
    private function getAvailableQuantity(array $inventory): ?int
    {
        if ($inventory === []) {
            return null;
        }

        return array_sum(array_map(
            static fn (array $warehouse): int => (int) ($warehouse['quantity'] ?? 0),
            $inventory
        ));
    }

}
