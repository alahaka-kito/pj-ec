<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * TikTok商品マスタの情報を商品マスタと販売マスタへ登録するサービス。
 */
class TikTokMasterRegistrationService
{
    /**
     * まだ商品マスタにないTikTok商品をすべて登録する。
     *
     * @return array{registered:int,skipped:int} 登録件数と対象外件数
     */
    public function registerAll(): array
    {
        $products = DB::table('tiktok_product_master')->orderBy('id')->pluck('id');
        $registered = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($products as $productId) {
            try {
                $result = $this->register((int) $productId);
                $result['created'] ? $registered++ : $updated++;
            } catch (RuntimeException $exception) {
                $skipped++;
                report($exception);
            }
        }

        return ['registered' => $registered, 'updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * 指定されたTikTok商品を商品マスタ、販売マスタ、バリエーションへ登録する。
     *
     * @param int $tiktokMasterId TikTok商品マスタの主キー
     * @return array{product_seq:int,sales_master_id:int} 作成した各マスタのID
     * @throws RuntimeException 仕入先がない、または対象商品の登録が不完全な場合
     */
    public function register(int $tiktokMasterId): array
    {
        return DB::transaction(function () use ($tiktokMasterId): array {
            $tiktok = DB::table('tiktok_product_master')->where('id', $tiktokMasterId)->lockForUpdate()->first();
            if (!$tiktok) {
                throw new RuntimeException('TikTok商品が見つかりません。');
            }

            $supplier = DB::table('supplier_master')->whereNull('deleted_at')->orderBy('seq')->first();
            if (!$supplier) {
                throw new RuntimeException('商品マスタ登録に必要な仕入先がありません。');
            }

            $raw = json_decode($tiktok->raw_data, true);
            if (!is_array($raw)) {
                throw new RuntimeException('TikTok商品のraw_dataを読み取れません。');
            }
            $skus = is_array($raw['skus'] ?? null) ? $raw['skus'] : [];
            $variantSkus = array_values(array_filter($skus, static function (array $sku): bool {
                $attributes = $sku['sales_attributes'] ?? [];
                return is_array($attributes) && $attributes !== [];
            }));
            // 複数SKUを持つ商品だけを販売マスタのバリエーション商品として扱う。
            $hasVariations = count($variantSkus) > 1;
            $hasGramVariation = false;
            foreach ($variantSkus as $sku) {
                foreach (($sku['sales_attributes'] ?? []) as $attribute) {
                    if (stripos((string) ($attribute['value_name'] ?? ''), 'g') !== false) {
                        $hasGramVariation = true;
                        break 2;
                    }
                }
            }
            $skuData = json_decode((string) $tiktok->sku_data, true);
            $productStock = (int) $tiktok->total_stock;
            if ($hasVariations && !$hasGramVariation && isset($skuData[0]['stock'])) {
                $productStock = (int) $skuData[0]['stock'];
            }

            $product = DB::table('product_master')->where('product_management_code_tiktok', $tiktok->product_id)->lockForUpdate()->first();
            $now = now();
            $productValues = [
                'image_path' => $tiktok->image_path,
                'management_code' => $supplier->management_code,
                'supplier_product_name' => $tiktok->product_name,
                'buying_price' => 0,
                'stock' => $productStock,
                'selling_places' => json_encode(['Tiktok'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'deleted_at' => null,
                'updated_at' => $now,
            ];
            if ($product) {
                $productSeq = (int) $product->seq;
                DB::table('product_master')->where('seq', $productSeq)->update($productValues);
            } else {
                $productSeq = (int) DB::table('product_master')->insertGetId($productValues + [
                    'product_management_code_tiktok' => $tiktok->product_id,
                    'product_management_code_amazon' => '',
                    'created_at' => $now,
                ]);
            }

            // バリエーションのない単体商品には販売マスタを作成しない。
            if (!$hasVariations) {
                $linkedVariations = DB::table('sales_variations as variation')
                    ->join('sales_variation_products as item', 'item.sales_variation_id', '=', 'variation.id')
                    ->where('item.product_master_seq', $productSeq)
                    ->get(['variation.id', 'variation.sales_master_id']);
                foreach ($linkedVariations as $linkedVariation) {
                    DB::table('sales_variation_products')
                        ->where('sales_variation_id', $linkedVariation->id)
                        ->where('product_master_seq', $productSeq)
                        ->delete();
                    if (!DB::table('sales_variation_products')->where('sales_variation_id', $linkedVariation->id)->exists()) {
                        DB::table('sales_variations')->where('id', $linkedVariation->id)->delete();
                    }
                    if (!DB::table('sales_variations')->where('sales_master_id', $linkedVariation->sales_master_id)->exists()) {
                        DB::table('sales_masters')->where('id', $linkedVariation->sales_master_id)->delete();
                    }
                }
                return ['product_seq' => $productSeq, 'sales_master_id' => null, 'created' => !$product];
            }

            $firstName = '';
            foreach ($variantSkus as $sku) {
                $attribute = $sku['sales_attributes'][0] ?? [];
                if (!empty($attribute['name'])) {
                    $firstName = (string) $attribute['name'];
                    break;
                }
            }
            $salesName = mb_substr(trim((string) $tiktok->product_name . ($firstName !== '' ? ' ' . $firstName : '')), 0, 255);

            $existingSalesId = DB::table('sales_variations as variation')
                ->join('sales_variation_products as item', 'item.sales_variation_id', '=', 'variation.id')
                ->where('item.product_master_seq', $productSeq)
                ->orderBy('variation.id')
                ->value('variation.sales_master_id');

            if ($existingSalesId) {
                $salesId = (int) $existingSalesId;
                DB::table('sales_masters')->where('id', $salesId)->update(['product_name' => $salesName, 'updated_at' => $now]);
                DB::table('sales_variations')->where('sales_master_id', $salesId)->delete();
            } else {
                $salesId = (int) DB::table('sales_masters')->insertGetId([
                    'product_name' => $salesName, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            foreach ($variantSkus as $sku) {
                $attributes = $sku['sales_attributes'] ?? [];
                $attribute = $attributes[0] ?? [];
                $valueName = (string) ($attribute['value_name'] ?? '');
                $quantity = preg_match('/\d+/', $valueName, $matches) ? max(1, (int) $matches[0]) : 1;
                $inventory = is_array($sku['inventory'] ?? null) ? $sku['inventory'] : [];
                $stock = array_sum(array_map(static fn ($item): int => (int) ($item['quantity'] ?? 0), $inventory));
                $variationId = DB::table('sales_variations')->insertGetId([
                    'sales_master_id' => $salesId,
                    'variation_name' => mb_substr($valueName, 0, 255),
                    'tikTok_sku_id' => (string) ($sku['id'] ?? ''),
                    'amazon_sku_id' => '',
                    'variation_stock_linking' => stripos($valueName, 'g') === false ? 1 : 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('sales_variation_products')->insert([
                    'sales_variation_id' => $variationId,
                    'product_master_seq' => $productSeq,
                    'quantity' => $quantity,
                    'variation_stock' => $stock,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return ['product_seq' => $productSeq, 'sales_master_id' => $salesId, 'created' => !$product];
        });
    }
}
