<?php

namespace App\Console\Commands;

use App\Services\AmazonOrderService;
use App\Services\SlackNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Amazonの新規注文を保存し、Slackに注文IDを通知するArtisanコマンド。
 */
class SyncAmazonOrders extends Command
{
    protected $signature = 'amazon-sync-orders';

    protected $description = 'Fetch Amazon orders created in the last day, save new orders, and notify Slack';

    /**
     * 直近1日分の注文を取得し、未保存の注文をDBへ登録して結果をSlackへ通知する。
     *
     * @param AmazonOrderService $orders Amazon注文取得サービス
     * @param SlackNotificationService $slack Slack通知サービス
     * @return int 同期と通知が成功した場合はSUCCESS、それ以外はFAILURE
     */
    public function handle(AmazonOrderService $orders, SlackNotificationService $slack): int
    {
        try {
            $apiOrders = $orders->fetchFormattedOrders(1);
        } catch (Throwable $exception) {
            Log::error('Amazon注文の同期に失敗しました。', ['exception' => $exception->getMessage()]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $newOrderIds = [];
        foreach ($apiOrders as $order) {
            $orderId = $order['AmazonOrderId'] ?? null;
            if (empty($orderId)) {
                Log::warning('注文IDがないAmazon注文をスキップしました。', ['order' => $order]);
                continue;
            }

            $created = DB::table('amazon_orders')->insertOrIgnore([
                'id' => (string) $orderId,
                'status' => (string) ($order['OrderStatus'] ?? 'UNKNOWN'),
                'product_name' => $order['OrderItems'][0]['Title'] ?? null,
                'total_amount' => $order['OrderTotal']['Amount'] ?? null,
                'paid_time' => !empty($order['PurchaseDate']) ? date('Y-m-d H:i:s', strtotime($order['PurchaseDate'])) : null,
                'raw_data' => json_encode($order, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if ($created > 0) {
                $newOrderIds[] = (string) $orderId;
            }
        }

        $messageLines = $newOrderIds !== []
            ? array_merge(['新規注文があります。'], $newOrderIds)
            : ['新規注文はありません。'];
        if (!$slack->posttoslack($messageLines, 'Amazon注文確認')) {
            $this->error('注文は保存されましたが、Slack通知に失敗しました。');

            return self::FAILURE;
        }

        $this->info(sprintf('取得した注文数: %d件、新規保存した注文数: %d件', count($apiOrders), count($newOrderIds)));

        return self::SUCCESS;
    }
}
