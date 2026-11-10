<?php

namespace App\Console\Commands;

use App\Services\TikTokProductMasterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * TikTok Shopの商品情報を取得して tiktok_product_master に同期するコマンド。
 */
class SyncTikTokProducts extends Command
{
    protected $signature = 'tiktok-sync-products';

    protected $description = 'TikTok Shopの商品情報を全件取得して保存する';

    /**
     * 商品情報を同期し、取得件数と保存件数をコンソールへ表示する。
     *
     * @return int 同期成功時はSUCCESS、例外発生時はFAILURE
     */
    public function handle(TikTokProductMasterService $products): int
    {
        try {
            $result = $products->sync();
        } catch (Throwable $exception) {
            Log::error('TikTok商品情報の同期に失敗しました。', ['exception' => $exception->getMessage()]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('取得商品数: %d件 / 保存商品数: %d件', $result['fetched'], $result['saved']));

        return self::SUCCESS;
    }
}
