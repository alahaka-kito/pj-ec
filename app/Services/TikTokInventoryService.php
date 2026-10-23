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
     * TikTokのSKU在庫とシステムのバリエーション在庫の差分から商品在庫を増減し、
     * 残った商品在庫と使用数量で各バリエーション在庫を再計算する。
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
        $skuIds = array_values(array_unique($skuIds));

        $variations = DB::table('sales_variations')
            ->whereIn('sku_id', $skuIds)
            ->get(['id', 'sku_id']);
        $variationsBySku = [];
        foreach ($variations as $variation) {
            $variationsBySku[(string) $variation->sku_id] = $variation;
        }

        $variationProducts = $variations->isEmpty()
            ? collect()
            : DB::table('sales_variation_products')
                ->whereIn('sales_variation_id', $variations->pluck('id')->all())
                ->get(['id', 'sales_variation_id', 'product_management_code', 'quantity', 'variation_stock']);
        $productsByVariation = $variationProducts->groupBy('sales_variation_id');

        $productCodes = $variationProducts->pluck('product_management_code')
            ->merge(collect($products)->pluck('id'))
            ->filter()
            ->map(static fn ($code) => (string) $code)
            ->unique()
            ->values()
            ->all();
        $masterStocks = DB::table('product_master')
            ->whereNull('deleted_at')
            ->whereIn('product_management_code', $productCodes)
            ->get(['product_management_code', 'stock'])
            ->mapWithKeys(static fn ($row) => [(string) $row->product_management_code => (int) $row->stock]);

        $stockDeltas = [];
        foreach ($products as $product) {
            $productId = (string) ($product['id'] ?? '');
            foreach (($product['skus'] ?? []) as $sku) {
                $skuStock = $this->getAvailableQuantity($sku['inventory'] ?? []);
                if ($skuStock === null) {
                    continue;
                }

                $skuId = (string) ($sku['id'] ?? '');
                $variation = $variationsBySku[$skuId] ?? null;
                if ($variation) {
                    $items = $productsByVariation->get($variation->id, collect());
                    if ($items->isEmpty()) {
                        continue;
                    }

                    $currentVariationStock = (int) $items->min('variation_stock');
                    $delta = $skuStock - $currentVariationStock;
                    if ($delta === 0) {
                        continue;
                    }

                    foreach ($items as $item) {
                        $code = (string) $item->product_management_code;
                        if ($code !== $productId || !$masterStocks->has($code)) {
                            continue;
                        }

                        $stockDeltas[$code] = ($stockDeltas[$code] ?? 0)
                            + ($delta * max(1, (int) $item->quantity));
                    }
                    continue;
                }

                if ($productId === '' || !$masterStocks->has($productId)) {
                    continue;
                }

                $delta = $skuStock - (int) $masterStocks->get($productId);
                if ($delta !== 0) {
                    $stockDeltas[$productId] = ($stockDeltas[$productId] ?? 0) + $delta;
                }
            }
        }

        $updatedProducts = 0;
        $updatedVariations = 0;
        DB::transaction(function () use ($stockDeltas, $masterStocks, &$updatedProducts, &$updatedVariations): void {
            $affectedCodes = [];
            foreach ($stockDeltas as $productCode => $delta) {
                $productCode = (string) $productCode;
                $currentStock = (int) $masterStocks->get($productCode, 0);
                $newStock = max(0, $currentStock + (int) $delta);
                if ($newStock === $currentStock) {
                    continue;
                }

                $updatedProducts += DB::table('product_master')
                    ->whereRaw('CAST(product_management_code AS CHAR) = ?', [$productCode])
                    ->whereNull('deleted_at')
                    ->update(['stock' => $newStock, 'updated_at' => now()]);
                $masterStocks[$productCode] = $newStock;
                $affectedCodes[] = $productCode;
            }

            $updatedVariations += $this->recalculateVariationStocks($affectedCodes, $masterStocks);
        });

        return [
            'products' => $products,
            'updated_products' => $updatedProducts,
            'updated_variations' => $updatedVariations,
        ];
    }

    /**
     * 更新後の商品在庫数と各構成商品の使用数量から、関連バリエーション在庫を再計算する。
     * 使用数量が商品在庫数を上回る場合は在庫を0にする。
     *
     * @param array<int, string> $productCodes 在庫数が変わった商品管理コード
     * @param \Illuminate\Support\Collection $masterStocks 商品管理コードごとの最新商品在庫
     * @return int 更新されたバリエーション商品行数
     */
    private function recalculateVariationStocks(array $productCodes, $masterStocks): int
    {
        $updated = 0;
        foreach (array_unique($productCodes) as $productCode) {
            $productCode = (string) $productCode;
            $productStock = (int) $masterStocks->get($productCode, 0);
            $items = DB::table('sales_variation_products')
                ->whereRaw('CAST(product_management_code AS CHAR) = ?', [$productCode])
                ->get(['id', 'quantity']);

            foreach ($items as $item) {
                $usageQuantity = max(1, (int) $item->quantity);
                $variationStock = intdiv($productStock, $usageQuantity);
                $updated += DB::table('sales_variation_products')
                    ->where('id', $item->id)
                    ->update([
                        'variation_stock' => $variationStock,
                        'updated_at' => now(),
                    ]);
            }
        }

        return $updated;
    }

    /**
     * 販売マスタのバリエーション在庫をTikTok ShopのSKU在庫へ反映する。
     *
     * TikTokのSKU IDは sales_variations.sku_id と一致するものを優先し、
     * 見つからない場合は sales_variation_products.product_management_code でも照合する。
     * セットSKUは構成商品の variation_stock のうち最も少ない値を採用する。
     *
     * @return array{updated: int, skipped: int, failed: int, rows: array<int, array<string, mixed>>}
     */
    public function pushInventory(): array
    {
        $products = $this->fetchInventory();
        $tokenRow = DB::table('tiktok_tokens')->latest('updated_at')->first();
        if (!$tokenRow || empty($tokenRow->access_token)) {
            throw new RuntimeException('TikTokのアクセストークンがありません。先に /tiktok/auth で認証してください。');
        }
        $tokenRow = $this->orders->ensureValidAccessToken($tokenRow);
        $stocksBySku = $this->buildPushStocksBySku($products);

        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $rows = [];

        foreach ($products as $product) {
            $productId = (string) ($product['id'] ?? '');
            if ($productId === '') {
                continue;
            }

            $skusPayload = [];
            $pendingRows = [];
            foreach (($product['skus'] ?? []) as $sku) {
                $skuId = (string) ($sku['id'] ?? '');
                if ($skuId === '' || !array_key_exists($skuId, $stocksBySku)) {
                    continue;
                }

                $quantity = max(0, min(99998, (int) $stocksBySku[$skuId]));
                $currentQuantity = $this->getAvailableQuantity($sku['inventory'] ?? []);
                $inventoryPayload = $this->buildWarehouseInventory($sku['inventory'] ?? [], $quantity);
                $row = [
                    'product_id' => $productId,
                    'product_title' => $product['title'] ?? '',
                    'sku_id' => $skuId,
                    'seller_sku' => $sku['seller_sku'] ?? '',
                    'current_quantity' => $currentQuantity,
                    'target_quantity' => $quantity,
                    'status' => 'skipped',
                    'message' => '',
                ];

                if ($inventoryPayload === []) {
                    $row['message'] = '倉庫情報がありません';
                    $skipped++;
                    $rows[] = $row;
                    continue;
                }

                if ($currentQuantity !== null && $currentQuantity === $quantity) {
                    $row['message'] = '在庫数は一致しています';
                    $skipped++;
                    $rows[] = $row;
                    continue;
                }

                $skusPayload[] = [
                    'id' => $skuId,
                    'inventory' => $inventoryPayload,
                ];
                $pendingRows[$skuId] = $row;
            }

            if ($skusPayload === []) {
                continue;
            }

            try {
                $responseData = $this->updateProductInventory($tokenRow, $productId, $skusPayload);
            } catch (RuntimeException $exception) {
                foreach ($pendingRows as $row) {
                    $row['status'] = 'failed';
                    $row['message'] = $exception->getMessage();
                    $failed++;
                    $rows[] = $row;
                }
                continue;
            }

            $errorBySku = [];
            foreach (($responseData['data']['errors'] ?? []) as $error) {
                $errorSkuId = (string) ($error['detail']['sku_id'] ?? $error['sku_id'] ?? '');
                if ($errorSkuId !== '') {
                    $errorBySku[$errorSkuId] = (string) ($error['message'] ?? 'TikTok在庫更新に失敗しました');
                }
            }

            foreach ($pendingRows as $skuId => $row) {
                if (isset($errorBySku[$skuId])) {
                    $row['status'] = 'failed';
                    $row['message'] = $errorBySku[$skuId];
                    $failed++;
                } else {
                    $row['status'] = 'updated';
                    $row['message'] = '更新しました';
                    $updated++;
                }
                $rows[] = $row;
            }
        }

        Log::info('TikTokへバリエーション在庫を反映しました。', [
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);

        return [
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'rows' => $rows,
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
     * TikTokのSKU IDに対応する、システム側の反映対象在庫数を組み立てる。
     *
     * @param array<int, array<string, mixed>> $products TikTok商品一覧
     * @return array<string, int> SKU ID => variation_stock
     */
    private function buildPushStocksBySku(array $products): array
    {
        $skuIds = [];
        foreach ($products as $product) {
            foreach (($product['skus'] ?? []) as $sku) {
                if (!empty($sku['id'])) {
                    $skuIds[] = (string) $sku['id'];
                }
            }
        }
        $skuIds = array_values(array_unique($skuIds));
        if ($skuIds === []) {
            return [];
        }

        $variations = DB::table('sales_variations')
            ->whereIn('sku_id', $skuIds)
            ->get(['id', 'sku_id']);
        $variationProducts = DB::table('sales_variation_products')
            ->where(function ($query) use ($variations, $skuIds): void {
                if ($variations->isNotEmpty()) {
                    $query->whereIn('sales_variation_id', $variations->pluck('id')->all());
                }
                $query->orWhereIn('product_management_code', $skuIds);
            })
            ->get(['sales_variation_id', 'product_management_code', 'variation_stock']);

        $stocksBySku = [];
        foreach ($variations as $variation) {
            $stocks = $variationProducts
                ->where('sales_variation_id', $variation->id)
                ->pluck('variation_stock')
                ->filter(static fn ($stock) => $stock !== null);
            if ($stocks->isEmpty()) {
                continue;
            }

            $stocksBySku[(string) $variation->sku_id] = (int) $stocks->min();
        }

        foreach ($variationProducts as $item) {
            $code = (string) $item->product_management_code;
            if (!in_array($code, $skuIds, true) || array_key_exists($code, $stocksBySku) || $item->variation_stock === null) {
                continue;
            }

            $stocksBySku[$code] = (int) $item->variation_stock;
        }

        return $stocksBySku;
    }

    /**
     * 対象在庫数を、SKUに紐づく全倉庫へ割り当てるペイロードを作る。
     *
     * 複数倉庫がある場合は合計が対象在庫数になるよう、在庫が最も多い倉庫へ集約する。
     *
     * @param array<int, array<string, mixed>> $inventory SKUの倉庫別在庫
     * @return array<int, array{warehouse_id: string, quantity: int}>
     */
    private function buildWarehouseInventory(array $inventory, int $quantity): array
    {
        $warehouses = [];
        foreach ($inventory as $warehouse) {
            $warehouseId = (string) ($warehouse['warehouse_id'] ?? '');
            if ($warehouseId === '') {
                continue;
            }

            $warehouses[] = [
                'warehouse_id' => $warehouseId,
                'quantity' => (int) ($warehouse['quantity'] ?? 0),
            ];
        }

        if ($warehouses === []) {
            return [];
        }

        $targetIndex = 0;
        $maxQuantity = $warehouses[0]['quantity'];
        foreach ($warehouses as $index => $warehouse) {
            if ($warehouse['quantity'] > $maxQuantity) {
                $maxQuantity = $warehouse['quantity'];
                $targetIndex = $index;
            }
        }

        $payload = [];
        foreach ($warehouses as $index => $warehouse) {
            $payload[] = [
                'warehouse_id' => $warehouse['warehouse_id'],
                'quantity' => $index === $targetIndex ? $quantity : 0,
            ];
        }

        return $payload;
    }

    /**
     * 商品単位でSKU在庫を更新する。
     *
     * @param object $tokenRow 有効なショップ認証情報
     * @param string $productId TikTok Shopの商品ID
     * @param array<int, array<string, mixed>> $skus 更新対象SKU
     * @return array<string, mixed>
     */
    private function updateProductInventory(object $tokenRow, string $productId, array $skus): array
    {
        $path = '/product/202309/products/' . $productId . '/inventory/update';
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
        $body = json_encode(['skus' => $skus], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $query['sign'] = hash_hmac(
            'sha256',
            config('services.tiktok.app_secret') . $path . $parameterString . $body . config('services.tiktok.app_secret'),
            config('services.tiktok.app_secret')
        );

        $response = Http::withHeaders([
            'x-tts-access-token' => $tokenRow->access_token,
            'Content-Type' => 'application/json',
        ])->withBody($body, 'application/json')->post(
            'https://open-api.tiktokglobalshop.com' . $path . '?' . http_build_query($query)
        );

        $responseData = $response->json();
        if (!$response->successful() || !is_array($responseData) || (int) ($responseData['code'] ?? -1) !== 0) {
            Log::error('TikTok在庫更新APIに失敗しました。', [
                'product_id' => $productId,
                'response' => $responseData,
            ]);
            throw new RuntimeException('TikTok在庫更新APIに失敗しました: ' . ($responseData['message'] ?? $response->status()));
        }

        return $responseData;
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
