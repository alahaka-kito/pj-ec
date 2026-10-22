<?php

namespace App\Console\Commands;

use App\Services\TikTokInventoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * TikTok ShopからSKU在庫情報を取得してコンソールに表示するコマンド。
 */
class SyncTikTokInventory extends Command
{
    protected $signature = 'tiktok-sync-inventory';

    protected $description = 'Fetch and display TikTok product inventory';

    /**
     * TikTokから在庫情報を取得し、商品・SKU・倉庫ごとの数量を表示する。
     *
     * @return int 同期が成功した場合はSUCCESS、例外発生時はFAILURE
     */
    public function handle(TikTokInventoryService $inventory): int
    {
        try {
            $result = $inventory->syncInventory();
        } catch (Throwable $exception) {
            Log::error('TikTok在庫同期に失敗しました。', ['exception' => $exception->getMessage()]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $products = $result['products'];
        $rows = [];
        foreach ($products as $product) {
            foreach (($product['skus'] ?? []) as $sku) {
                $warehouses = $sku['inventory'] ?? [];
                if ($warehouses === []) {
                    $rows[] = [
                        $product['id'] ?? '',
                        $product['title'] ?? '',
                        $this->formatVariationName($sku['sales_attributes'] ?? []),
                        $sku['id'] ?? '',
                        $sku['seller_sku'] ?? '',
                        '',
                        '',
                    ];
                    continue;
                }

                foreach ($warehouses as $warehouse) {
                    $rows[] = [
                        $product['id'] ?? '',
                        $product['title'] ?? '',
                        $this->formatVariationName($sku['sales_attributes'] ?? []),
                        $sku['id'] ?? '',
                        $sku['seller_sku'] ?? '',
                        $warehouse['warehouse_id'] ?? '',
                        $warehouse['quantity'] ?? '',
                    ];
                }
            }
        }

        $this->info(sprintf('取得商品数: %d件、在庫行数: %d件', count($products), count($rows)));
        $this->info(sprintf(
            '商品マスタ更新数: %d件、バリエーション在庫更新数: %d件',
            $result['updated_products'],
            $result['updated_variations']
        ));
        $this->table(['商品ID', '商品名', 'バリエーション名', 'SKU ID', 'seller_sku', '倉庫ID', '在庫数'], $rows);

        return self::SUCCESS;
    }

    /**
     * APIのsales_attributesから画面表示用のバリエーション名を組み立てる。
     *
     * @param array<int, array<string, mixed>> $attributes SKUの販売属性
     */
    private function formatVariationName(array $attributes): string
    {
        $names = [];
        foreach ($attributes as $attribute) {
            $value = $attribute['value_name'] ?? $attribute['value'] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $name = $attribute['name'] ?? null;
            $names[] = $name ? $name . ': ' . $value : (string) $value;
        }

        return implode(' / ', $names);
    }
}
