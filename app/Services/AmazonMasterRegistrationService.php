<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Amazon商品マスタの親子SKUを商品マスタと販売マスタへ登録するサービス。
 */
class AmazonMasterRegistrationService
{
    /**
     * Amazon商品マスタにある親SKUを商品マスタへ登録し、子SKUを販売マスタへ反映する。
     *
     * @return array{registered:int,updated:int,skipped:int} 新規登録・更新・スキップ件数
     */
    public function registerAll(): array
    {
        $parents = DB::table('amazon_product_master')->whereNull('parent_sku')->orderBy('id')->get();
        $registered = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($parents as $parent) {
            try {
                $result = $this->registerParent((string) $parent->seller_sku);
                $result['created'] ? $registered++ : $updated++;
            } catch (RuntimeException $exception) {
                $skipped++;
                report($exception);
            }
        }

        return ['registered' => $registered, 'updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * 親SKUを商品マスタへ反映し、その親に紐づく子SKUから販売バリエーションを作成・更新する。
     *
     * @param string $parentSku Amazonの親SKU
     * @return array{product_seq:int,sales_master_id:int|null,created:bool} 登録したマスタIDと新規作成状態
     * @throws RuntimeException 親SKUまたは仕入先が見つからない場合
     */
    private function registerParent(string $parentSku): array
    {
        return DB::transaction(function () use ($parentSku): array {
            $parent = DB::table('amazon_product_master')->where('seller_sku', $parentSku)->lockForUpdate()->first();
            if (!$parent) {
                throw new RuntimeException("Amazon親SKUが見つかりません: {$parentSku}");
            }
            $supplier = DB::table('supplier_master')->whereNull('deleted_at')->orderBy('seq')->first();
            if (!$supplier) {
                throw new RuntimeException('商品マスタ登録に必要な仕入先がありません。');
            }

            $children = DB::table('amazon_product_master')->where('parent_sku', $parentSku)->orderBy('id')->get();
            $stock = (int) $parent->total_stock;
            if ($children->isNotEmpty() && stripos((string) $parent->product_name, 'g') === false) {
                $stock = (int) $children->max('total_stock');
            }

            $product = DB::table('product_master')
                ->where('product_management_code_amazon', $parent->seller_sku)
                ->lockForUpdate()->first();
            $now = now();
            $productValues = [
                'image_path' => $parent->image_path,
                'management_code' => $supplier->management_code,
                'supplier_product_name' => $parent->product_name,
                'buying_price' => 0,
                'stock' => $stock,
                'size' => $parent->shipping_size,
                'selling_places' => json_encode(['Amazon'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'deleted_at' => null,
                'updated_at' => $now,
            ];

            if ($product) {
                $productSeq = (int) $product->seq;
                DB::table('product_master')->where('seq', $productSeq)->update($productValues);
            } else {
                $productSeq = (int) DB::table('product_master')->insertGetId($productValues + [
                    'product_management_code_tiktok' => '',
                    'product_management_code_amazon' => $parent->seller_sku,
                    'created_at' => $now,
                ]);
            }

            if ($children->isEmpty()) {
                $this->removeOrphanedSalesVariations($productSeq);
                return ['product_seq' => $productSeq, 'sales_master_id' => null, 'created' => !$product];
            }

            $childSkus = $children->pluck('seller_sku')->all();
            $salesId = DB::table('sales_variations')
                ->whereIn('amazon_sku_id', $childSkus)
                ->orderBy('id')
                ->value('sales_master_id');

            if ($salesId) {
                $salesId = (int) $salesId;
                DB::table('sales_masters')->where('id', $salesId)->update([
                    'product_name' => mb_substr((string) $parent->product_name, 0, 255),
                    'updated_at' => $now,
                ]);
                $oldAmazonVariationIds = DB::table('sales_variations as variation')
                    ->join('sales_variation_products as item', 'item.sales_variation_id', '=', 'variation.id')
                    ->where('variation.sales_master_id', $salesId)
                    ->where('item.product_master_seq', $productSeq)
                    ->whereIn('variation.amazon_sku_id', $childSkus)
                    ->pluck('variation.id');
                DB::table('sales_variations')->whereIn('id', $oldAmazonVariationIds)->delete();
            } else {
                $salesId = (int) DB::table('sales_masters')->insertGetId([
                    'product_name' => mb_substr((string) $parent->product_name, 0, 255),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($children as $child) {
                $variationId = DB::table('sales_variations')->insertGetId([
                    'sales_master_id' => $salesId,
                    'variation_name' => mb_substr((string) $child->product_name, 0, 255),
                    'tikTok_sku_id' => '',
                    'amazon_sku_id' => (string) $child->seller_sku,
                    'variation_stock_linking' => stripos((string) $child->product_name, 'g') === false ? 1 : 0,
                    'shipping_size' => $child->shipping_size,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('sales_variation_products')->insert([
                    'sales_variation_id' => $variationId,
                    'product_master_seq' => $productSeq,
                    'quantity' => $this->extractQuantity((string) $child->product_name),
                    'variation_stock' => (int) $child->total_stock,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return ['product_seq' => $productSeq, 'sales_master_id' => $salesId, 'created' => !$product];
        });
    }

    /**
     * 商品が単体SKUになった場合、販売マスタからその商品の構成を除去し、空になった販売マスタを削除する。
     *
     * @param int $productSeq 商品マスタの主キー
     */
    private function removeOrphanedSalesVariations(int $productSeq): void
    {
        $variations = DB::table('sales_variations as variation')
            ->join('sales_variation_products as item', 'item.sales_variation_id', '=', 'variation.id')
            ->where('item.product_master_seq', $productSeq)
            ->where('variation.amazon_sku_id', '<>', '')
            ->get(['variation.id', 'variation.sales_master_id']);

        foreach ($variations as $variation) {
            DB::table('sales_variation_products')->where('sales_variation_id', $variation->id)
                ->where('product_master_seq', $productSeq)->delete();
            if (!DB::table('sales_variation_products')->where('sales_variation_id', $variation->id)->exists()) {
                DB::table('sales_variations')->where('id', $variation->id)->delete();
            }
            if (!DB::table('sales_variations')->where('sales_master_id', $variation->sales_master_id)->exists()) {
                DB::table('sales_masters')->where('id', $variation->sales_master_id)->delete();
            }
        }
    }

    /**
     * 商品名から使用数量を読み取る。複数単位がある場合はkg、キログラム、本の順で採用する。
     *
     * @param string $productName 子SKUの商品名
     * @return int 使用数量。数量を読み取れない場合は1
     */
    private function extractQuantity(string $productName): int
    {
        $normalized = preg_replace('/[\s,，]+/u', '', $productName) ?? $productName;
        foreach (['kg', 'キログラム', '本'] as $unit) {
            if (preg_match('/(\d+(?:\.\d+)?)' . preg_quote($unit, '/') . '/iu', $normalized, $matches) === 1) {
                return max(1, (int) $matches[1]);
            }
        }

        return 1;
    }
}
