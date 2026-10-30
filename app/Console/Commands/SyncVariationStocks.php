<?php

namespace App\Console\Commands;

use App\Services\TikTokInventoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 確定済みの商品マスタ在庫をもとにバリエーション在庫を再計算するコマンド。
 *
 * 在庫連動が有効なバリエーションのみを処理する。
 */
class SyncVariationStocks extends Command
{
    protected $signature = 'sync-variation-stock';

    protected $description = 'Recalculate linked variation stock from confirmed product stock';

    /**
     * バリエーション在庫数を再配分し、更新件数を表示する。
     *
     * @param TikTokInventoryService $inventory 在庫数の計算とデータベース更新を行うサービス。
     * @return int コマンドの成功または失敗を表す終了コード。
     */
    public function handle(TikTokInventoryService $inventory): int
    {
        try {
            $updated = $inventory->syncVariationStocks();
        } catch (Throwable $exception) {
            Log::error('バリエーション在庫数の再配分に失敗しました。', [
                'exception' => $exception->getMessage(),
            ]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('バリエーション在庫数更新数: %d件', $updated));

        return self::SUCCESS;
    }
}
