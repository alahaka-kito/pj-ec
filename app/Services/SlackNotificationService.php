<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Slack Incoming Webhookを使った通知を担当するサービス。
 */
class SlackNotificationService
{
    /**
     * タイトルと本文行をまとめてSlackへ送信する。
     * Webhook URLはservices.slack.webhook_url（SLACK_WEBHOOK_URL）から取得する。
     *
     * @param array<int, string> $lines Slackメッセージ本文に含める行
     * @param string $title Slackメッセージの先頭に表示するタイトル
     * @return bool Webhookへの送信が成功した場合はtrue
     */
    public function posttoslack(array $lines, string $title): bool
    {
        $webhookUrl = config('services.slack.webhook_url');

        if (empty($webhookUrl)) {
            Log::warning('SLACK_WEBHOOK_URLが設定されていないため、Slack通知をスキップしました。');
            return false;
        }

        $response = Http::asForm()->post($webhookUrl, [
            'payload' => json_encode([
                'text' => $title."\n".implode("\n", $lines),
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);

        if (!$response->successful() || $response->body() !== 'ok') {
            Log::error('Slack通知に失敗しました。', ['HTTPステータス' => $response->status(), 'レスポンス' => $response->body()]);
            return false;
        }

        return true;
    }
}
