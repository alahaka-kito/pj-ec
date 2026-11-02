<?php

namespace App\Console\Commands;

use App\Services\AmazonInventoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * AmazonからSKU別のFBA在庫を取得し、商品マスタへ反映するコマンド。
 */
class SyncAmazonInventory extends Command
{
    protected $signature = 'amazon-sync-inventory';

    protected $description = 'Amazon FBA在庫を取得して商品マスタへ反映';

    /**
     * 在庫同期を実行し、取得数と更新件数をコンソールへ表示する。
     *
     * @param AmazonInventoryService $inventory Amazon在庫取得と商品在庫更新を行うサービス。
     * @return int コマンドの成功または失敗を表す終了コード。
     */
    public function handle(AmazonInventoryService $inventory): int
    {
        try {
            $result = $inventory->syncInventory();
        } catch (Throwable $exception) {
            Log::error('Amazon在庫の同期に失敗しました。', ['exception' => $exception->getMessage()]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $rows = [];
        foreach ($result['summaries'] as $summary) {
            $rows[] = [
                $summary['sellerSku'] ?? '',
                $summary['asin'] ?? '',
                $summary['productName'] ?? '',
                $summary['inventoryDetails']['fulfillableQuantity'] ?? 0,
            ];
        }

        $this->info(sprintf('照合対象SKU数: %d件', $result['requested_skus']));
        $this->info(sprintf('Amazon取得SKU数: %d件', count($result['summaries'])));
        if ($result['requested_skus'] > 0 && $result['summaries'] === []) {
            $this->warn('在庫サマリーがありません。Amazon SKU IDの一致とFBA在庫の有無をご確認ください。');
        }
        $this->info(sprintf('商品マスタ更新数: %d件', $result['updated_products']));
        $this->info(sprintf('バリエーション在庫更新数: %d件', $result['updated_variations']));
        if ($rows !== []) {
            $this->table(['SKU ID(Amazon)', 'ASIN', '商品名', '販売可能在庫数'], $rows);
        }

        return self::SUCCESS;
    }
}
