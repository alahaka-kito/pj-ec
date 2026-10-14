<?php

namespace App\Console\Commands;

use App\Services\SlackNotificationService;
use App\Services\TikTokOrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * TikTokから直近1日分の注文を取得し、新規注文を保存してSlackへ通知するコマンド。
 */
class SyncTikTokOrders extends Command
{
    protected $signature = 'tiktok-sync-orders';

    protected $description = 'Fetch TikTok orders created in the last day, save new orders, and notify Slack';

    /**
     * 注文を取得・保存し、今回新たに保存した注文IDまたは新規注文がない旨をSlackへ通知する。
     *
     * @return int Artisanの終了コード。処理または通知に失敗した場合はFAILURE。
     */
    public function handle(TikTokOrderService $orders, SlackNotificationService $slack): int
    {
        try {
            $apiOrders = $orders->fetchFormattedOrders(1);
        } catch (\Throwable $exception) {
            Log::error('TikTok注文の同期に失敗しました。', ['例外' => $exception->getMessage()]);
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        if (isset($apiOrders['message']) || !is_array($apiOrders)) {
            Log::error('TikTok注文APIから注文一覧を取得できませんでした。', ['レスポンス' => $apiOrders]);
            $this->error('TikTok注文APIから注文一覧を取得できませんでした。');
            return self::FAILURE;
        }

        $newOrderIds = [];

        foreach ($apiOrders as $order) {
            if (empty($order['id'])) {
                Log::warning('注文IDがないため、TikTok注文をスキップしました。', ['注文' => $order]);
                continue;
            }

            $lineItem = $order['line_items'][0] ?? [];
            $created = DB::table('tiktok_orders')->insertOrIgnore([
                'id' => (string) $order['id'],
                'status' => (string) ($order['status'] ?? 'UNKNOWN'),
                'product_name' => $lineItem['product_name'] ?? null,
                'total_amount' => $order['payment']['total_amount'] ?? null,
                'paid_time' => !empty($order['paid_time']) ? date('Y-m-d H:i:s', (int) $order['paid_time']) : null,
                'raw_data' => json_encode(
                    $order,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                ),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($created > 0) {
                $newOrderIds[] = (string) $order['id'];
            }
        }

        $messageLines = $newOrderIds !== []
            ? array_merge(['新規注文があります。'], $newOrderIds)
            : ['新規注文がありません。'];
        $title = 'テストTikTok注文確認';

        $sent = $slack->posttoslack($messageLines, $title);
        if (!$sent) {
            $this->error('注文は保存されましたが、Slack通知に失敗しました。');
            return self::FAILURE;
        }

        $this->info(sprintf('取得した注文数: %d件、新規保存した注文数: %d件', count($apiOrders), count($newOrderIds)));
        return self::SUCCESS;
    }
}
