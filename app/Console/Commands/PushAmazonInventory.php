<?php

namespace App\Console\Commands;

use App\Services\AmazonInventoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * システムで管理しているバリエーション在庫をAmazonのFBM出品へ反映するコマンド
 */
class PushAmazonInventory extends Command
{
    protected $signature = 'amazon-push-inventory';

    protected $description = 'システム内の在庫数をAmazon FBM出品へ反映';

    /**
     * Amazonへの在庫送信を実行し、SKUごとの更新結果をコンソールに表示する。
     *
     * @param AmazonInventoryService $inventory Amazon在庫の取得・送信を行うサービス
     * @return int 全SKUの処理が成功した場合はSUCCESS、失敗SKUがある場合はFAILURE
     */
    public function handle(AmazonInventoryService $inventory): int
    {
        try {
            $result = $inventory->pushInventory();
        } catch (Throwable $exception) {
            Log::error('Amazon FBM在庫の送信に失敗しました。', ['exception' => $exception->getMessage()]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $rows = array_map(static fn (array $row): array => [
            $row['seller_sku'] ?? '',
            $row['current_quantity'] ?? '',
            $row['target_quantity'] ?? '',
            $row['status'] ?? '',
            $row['message'] ?? '',
        ], $result['rows']);

        $this->info(sprintf(
            '更新: %d件、スキップ: %d件、失敗: %d件',
            $result['updated'],
            $result['skipped'],
            $result['failed']
        ));
        if ($rows !== []) {
            $this->table(['Amazon SKU ID', '現在庫', '反映在庫', '結果', 'メッセージ'], $rows);
        }

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
