<?php

namespace App\Console\Commands;

use App\Services\AmazonProductMasterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Amazon SP-APIの商品情報を取得し、amazon_product_masterへ同期するコマンド。
 */
class SyncAmazonProducts extends Command
{
    protected $signature = 'amazon-sync-products';

    protected $description = 'Amazonの商品情報を取得して商品マスタへ保存する';

    /**
     * 商品同期を実行し、取得件数と保存件数を表示する。
     *
     * @return int 同期成功時はSUCCESS、例外発生時はFAILURE
     */
    public function handle(AmazonProductMasterService $products): int
    {
        try {
            $result = $products->sync();
        } catch (Throwable $exception) {
            Log::error('Amazon商品情報の同期に失敗しました。', ['exception' => $exception->getMessage()]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('取得商品数: %d件 / 保存商品数: %d件', $result['fetched'], $result['saved']));

        return self::SUCCESS;
    }
}
