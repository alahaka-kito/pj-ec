<?php

namespace App\Console\Commands;

use App\Services\TikTokInventoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * システムで管理しているバリエーション在庫をTikTok Shopへ反映するコマンド。
 */
class PushTikTokInventory extends Command
{
    protected $signature = 'tiktok-push-inventory';

    protected $description = 'Push variation stock from this system to TikTok Shop SKUs';

    /**
     * 販売マスタのバリエーション在庫をTikTok ShopのSKU在庫へ反映する。
     *
     * @return int 反映が完了した場合はSUCCESS、例外発生時はFAILURE
     */
    public function handle(TikTokInventoryService $inventory): int
    {
        try {
            $result = $inventory->pushInventory();
        } catch (Throwable $exception) {
            Log::error('TikTok在庫の反映に失敗しました。', ['exception' => $exception->getMessage()]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $rows = [];
        foreach ($result['rows'] as $row) {
            $rows[] = [
                $row['product_id'] ?? '',
                $row['product_title'] ?? '',
                $row['sku_id'] ?? '',
                $row['seller_sku'] ?? '',
                $row['current_quantity'] ?? '',
                $row['target_quantity'] ?? '',
                $row['status'] ?? '',
                $row['message'] ?? '',
            ];
        }

        $this->info(sprintf(
            '更新: %d件、スキップ: %d件、失敗: %d件',
            $result['updated'],
            $result['skipped'],
            $result['failed']
        ));
        if ($rows !== []) {
            $this->table(
                ['商品ID', '商品名', 'SKU ID', 'seller_sku', 'TikTok在庫', '反映在庫', '結果', 'メッセージ'],
                $rows
            );
        }

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
