<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Amazonから取得したFBA在庫を商品マスタと販売バリエーションへ反映するサービス。
 */
class AmazonInventoryService
{
    public function __construct(private AmazonOrderService $amazon)
    {
    }

    /**
     * Amazon SKUの販売可能在庫を取得し、登録済みSKUと照合して在庫を更新する。
     * 在庫連動が有効な場合は差分に使用数量を乗じて商品マスタへ反映し、
     * 無効な場合は商品マスタを変更せず、取得数をバリエーション在庫へ直接保存する。
     * バリエーションに未登録のSKUは、商品マスタのAmazon管理コードと照合して更新する。
     *
     * @return array{requested_skus: int, summaries: array<int, array<string, mixed>>, updated_products: int, updated_variations: int}
     */
    public function syncInventory(): array
    {
        $variations = DB::table('sales_variations')
            ->whereNotNull('amazon_sku_id')
            ->where('amazon_sku_id', '<>', '')
            ->get(['id', 'amazon_sku_id', 'variation_stock_linking']);
        $productSkus = DB::table('product_master')
            ->whereNull('deleted_at')
            ->whereNotNull('product_management_code_amazon')
            ->where('product_management_code_amazon', '<>', '')
            ->pluck('product_management_code_amazon');
        $skuIds = $variations->pluck('amazon_sku_id')
            ->merge($productSkus)
            ->map(static fn ($sku) => (string) $sku)
            ->unique()
            ->values()
            ->all();
        $summaries = $this->amazon->fetchSellerFulfilledInventories($skuIds);

        $variationsBySku = $variations->keyBy(static fn ($variation) => (string) $variation->amazon_sku_id);
        $variationProducts = $variations->isEmpty()
            ? collect()
            : DB::table('sales_variation_products as svp')
                ->join('product_master as pm', 'pm.seq', '=', 'svp.product_master_seq')
                ->whereIn('svp.sales_variation_id', $variations->pluck('id')->all())
                ->get([
                    'svp.id',
                    'svp.sales_variation_id',
                    'pm.product_management_code_amazon as product_management_code',
                    'svp.quantity',
                    'svp.variation_stock',
                ]);
        $productsByVariation = $variationProducts->groupBy('sales_variation_id');

        $productCodes = $variationProducts->pluck('product_management_code')
            ->merge(collect($summaries)->pluck('sellerSku'))
            ->filter()->map(static fn ($code) => (string) $code)->unique()->values()->all();
        $masterStocks = DB::table('product_master')->whereNull('deleted_at')
            ->whereIn('product_management_code_amazon', $productCodes)
            ->get(['product_management_code_amazon', 'stock'])
            ->mapWithKeys(static fn ($row) => [(string) $row->product_management_code_amazon => (int) $row->stock]);

        $stockDeltas = [];
        $directVariationStocks = [];
        foreach ($summaries as $summary) {
            $sellerSku = (string) ($summary['sellerSku'] ?? '');
            $quantity = $this->getSellableQuantity($summary);
            if ($sellerSku === '' || $quantity === null) {
                continue;
            }

            $variation = $variationsBySku->get($sellerSku);
            if ($variation) {
                $items = $productsByVariation->get($variation->id, collect());
                if ($items->isEmpty()) {
                    continue;
                }

                if (!(bool) $variation->variation_stock_linking) {
                    foreach ($items as $item) {
                        $directVariationStocks[(int) $item->id] = $quantity;
                    }
                    continue;
                }

                $delta = $quantity - (int) $items->min('variation_stock');
                if ($delta === 0) {
                    continue;
                }
                foreach ($items as $item) {
                    $code = (string) $item->product_management_code;
                    if ($code === '' || !$masterStocks->has($code)) {
                        continue;
                    }
                    $stockDeltas[$code] = ($stockDeltas[$code] ?? 0)
                        + ($delta * max(1, (int) $item->quantity));
                }
                continue;
            }

            if ($masterStocks->has($sellerSku)) {
                $delta = $quantity - (int) $masterStocks->get($sellerSku);
                if ($delta !== 0) {
                    $stockDeltas[$sellerSku] = ($stockDeltas[$sellerSku] ?? 0) + $delta;
                }
            }
        }

        $updatedProducts = 0;
        $updatedVariations = 0;
        DB::transaction(function () use ($stockDeltas, $directVariationStocks, $masterStocks, &$updatedProducts, &$updatedVariations): void {
            foreach ($stockDeltas as $productCode => $delta) {
                $currentStock = (int) $masterStocks->get($productCode, 0);
                $newStock = max(0, $currentStock + (int) $delta);
                if ($newStock === $currentStock) {
                    continue;
                }
                $updatedProducts += DB::table('product_master')
                    ->whereRaw('CAST(product_management_code_amazon AS CHAR) = ?', [$productCode])
                    ->whereNull('deleted_at')
                    ->update(['stock' => $newStock, 'updated_at' => now()]);
                $masterStocks[$productCode] = $newStock;
            }

            foreach ($directVariationStocks as $id => $quantity) {
                $updatedVariations += DB::table('sales_variation_products')->where('id', $id)
                    ->update(['variation_stock' => $quantity, 'updated_at' => now()]);
            }
        });

        Log::info('Amazon FBA在庫を同期しました。', [
            'summaries' => count($summaries),
            'updated_products' => $updatedProducts,
            'updated_variations' => $updatedVariations,
        ]);

        return [
            'requested_skus' => count($skuIds),
            'summaries' => $summaries,
            'updated_products' => $updatedProducts,
            'updated_variations' => $updatedVariations,
        ];
    }

    /**
     * システム内のAmazon SKU別在庫をFBM出品へ反映する。
     * バリエーションSKUは構成商品のvariation_stockの最小値を使用し、商品SKUは商品マスタ在庫を使用する。
     * 現在のAmazon在庫と同じ数量のSKUは更新せず、SKUごとの結果を返す。
     *
     * @return array{updated: int, skipped: int, failed: int, rows: array<int, array<string, mixed>>}
     */
    public function pushInventory(): array
    {
        $variations = DB::table('sales_variations')
            ->whereNotNull('amazon_sku_id')->where('amazon_sku_id', '<>', '')
            ->get(['id', 'amazon_sku_id']);
        $variationStocks = $variations->isEmpty()
            ? collect()
            : DB::table('sales_variation_products as svp')
                ->whereIn('svp.sales_variation_id', $variations->pluck('id')->all())
                ->get(['svp.sales_variation_id', 'svp.variation_stock'])
                ->groupBy('sales_variation_id')
                ->map(static fn ($items) => (int) $items->min('variation_stock'));

        $stocksBySku = [];
        foreach ($variations as $variation) {
            if ($variationStocks->has($variation->id)) {
                $stocksBySku[(string) $variation->amazon_sku_id] = $variationStocks->get($variation->id);
            }
        }

        $products = DB::table('product_master')
            ->whereNull('deleted_at')
            ->whereNotNull('product_management_code_amazon')
            ->where('product_management_code_amazon', '<>', '')
            ->get(['product_management_code_amazon', 'stock']);
        foreach ($products as $product) {
            $sku = (string) $product->product_management_code_amazon;
            $stocksBySku[$sku] ??= (int) $product->stock;
        }

        $listings = $this->amazon->fetchSellerFulfilledInventories(array_keys($stocksBySku));
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $rows = [];
        foreach ($listings as $listing) {
            $sku = (string) ($listing['sellerSku'] ?? '');
            if ($sku === '' || !array_key_exists($sku, $stocksBySku)) {
                continue;
            }

            $current = $this->getSellableQuantity($listing);
            $target = max(0, (int) $stocksBySku[$sku]);
            $row = [
                'seller_sku' => $sku,
                'current_quantity' => $current,
                'target_quantity' => $target,
                'status' => 'skipped',
                'message' => '',
            ];
            if ($current === null) {
                $row['message'] = '現在のAmazon在庫数を取得できませんでした';
                $skipped++;
            } elseif ($current === $target) {
                $row['message'] = '在庫数は一致しています';
                $skipped++;
            } else {
                try {
                    $this->amazon->updateSellerFulfilledInventory(
                        $sku,
                        (string) ($listing['productType'] ?? 'PRODUCT'),
                        $target
                    );
                    $row['status'] = 'updated';
                    $row['message'] = 'Amazonへ更新要求を送信しました';
                    $updated++;
                } catch (\Throwable $exception) {
                    $row['status'] = 'failed';
                    $row['message'] = $exception->getMessage();
                    $failed++;
                    Log::error('Amazon FBM在庫の更新に失敗しました。', [
                        'seller_sku' => $sku,
                        'exception' => $exception->getMessage(),
                    ]);
                }
            }
            $rows[] = $row;
        }

        Log::info('Amazon FBM出品へ在庫を送信しました。', compact('updated', 'skipped', 'failed'));

        return compact('updated', 'skipped', 'failed', 'rows');
    }

    /**
     * 在庫サマリーから注文処理に利用できる販売可能数を取得する。
     *
     * @param array<string, mixed> $summary Amazon在庫サマリー
     * @return int|null 在庫数。数量情報がないサマリーではnull。
     */
    private function getSellableQuantity(array $summary): ?int
    {
        $quantity = $summary['inventoryDetails']['fulfillableQuantity'] ?? null;
        if (!is_numeric($quantity)) {
            return null;
        }

        return max(0, (int) $quantity);
    }
}
