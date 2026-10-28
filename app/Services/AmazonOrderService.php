<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Amazon SP-APIから注文と注文商品を取得するサービス。
 * LWAリフレッシュトークンでアクセストークンを取得し、指定期間の注文を返す。
 */
class AmazonOrderService
{
    /**
     * 指定日数以内に作成された注文を取得し、各注文に商品情報を追加して返す。
     * SP-APIのページングにも対応する。
     *
     * @param int $days 取得対象とする直近の日数
     * @return array<int, array<string, mixed>> Amazonの注文データ一覧
     * @throws RuntimeException 認証設定不足、トークン取得失敗、またはAPI応答の異常時
     * @throws \Illuminate\Http\Client\RequestException Amazon APIがHTTPエラーを返した場合
     */
    public function fetchFormattedOrders(int $days = 1): array
    {
        $config = config('services.amazon');
        foreach (['lwa_client_id', 'lwa_client_secret', 'refresh_token', 'marketplace_id'] as $key) {
            if (empty($config[$key])) {
                throw new RuntimeException("Amazon SP-API configuration is missing: services.amazon.{$key}");
            }
        }

        $accessToken = $this->getAccessToken($config);
        $isSandbox = str_contains((string) parse_url($config['endpoint'], PHP_URL_HOST), 'sandbox.');
        // Orders API v0の静的サンドボックスは、次のテスト用リクエスト値にのみ一致する。
        $marketplaceId = $isSandbox ? 'ATVPDKIKX0DER' : $config['marketplace_id'];
        $createdAfter = $isSandbox
            ? 'TEST_CASE_200'
            : gmdate('Y-m-d\TH:i:s\Z', time() - max(1, $days) * 86400);
        $orders = [];
        $nextToken = null;

        do {
            $query = $nextToken !== null
                ? ['NextToken' => $nextToken]
                : ['MarketplaceIds' => $marketplaceId, 'CreatedAfter' => $createdAfter];
            $data = $this->get('/orders/v0/orders', $query, $accessToken, $config);
            $page = $data['payload']['Orders'] ?? null;
            if (!is_array($page)) {
                throw new RuntimeException('Failed to retrieve orders from Amazon SP-API.');
            }

            foreach ($page as $order) {
                $orderId = $order['AmazonOrderId'] ?? null;
                if (!is_string($orderId) || $orderId === '') {
                    $order['OrderItems'] = [];
                    $orders[] = $order;
                    continue;
                }

                // 注文商品取得APIの通常レート制限を超えないよう、リクエスト間隔を空ける。
                if ($orders !== []) {
                    sleep(2);
                }
                // 注文商品取得APIの静的モックに一致させるため、テスト用注文IDを指定する。
                $itemsOrderId = $isSandbox ? 'TEST_CASE_200' : $orderId;
                $itemsData = $this->get(
                    '/orders/v0/orders/'.rawurlencode($itemsOrderId).'/orderItems',
                    [],
                    $accessToken,
                    $config
                );
                $order['OrderItems'] = $itemsData['payload']['OrderItems'] ?? [];
                $orders[] = $order;
            }

            $nextToken = $data['payload']['NextToken'] ?? null;
        } while ($nextToken !== null && $nextToken !== '');

        return $orders;
    }

    /**
     * LWAのリフレッシュトークンを使ってSP-API用アクセストークンを取得する。
     *
     * @param array<string, mixed> $config Amazon接続設定
     * @return string 発行されたアクセストークン
     * @throws RuntimeException LWAがエラーを返した場合、またはトークンを取得できない場合
     */
    private function getAccessToken(array $config): string
    {
        $response = Http::asForm()->post('https://api.amazon.com/auth/o2/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $config['refresh_token'],
            'client_id' => $config['lwa_client_id'],
            'client_secret' => $config['lwa_client_secret'],
        ]);

        if ($response->json('error') === 'invalid_grant') {
            Log::error('Amazon LWA rejected the refresh token.', [
                'error' => $response->json('error'),
                'error_description' => $response->json('error_description'),
            ]);
            throw new RuntimeException('リフレッシュトークンを更新してください。');
        }

        $response->throw();
        $token = $response->json('access_token');
        if (!is_string($token) || $token === '') {
            Log::error('Amazon LWA token response did not include an access token.');
            throw new RuntimeException('リフレッシュトークンを更新してください。');
        }

        return $token;
    }

    /**
     * LWAアクセストークンを付けてSP-APIのGETリクエストを送信する。
     *
     * @param string $path APIのパス
     * @param array<string, scalar> $query クエリパラメーター
     * @param string $accessToken LWAアクセストークン
     * @param array<string, mixed> $config Amazon接続設定
     * @return array<string, mixed> JSONレスポンス
     * @throws RuntimeException JSON以外のレスポンスを受け取った場合
     * @throws \Illuminate\Http\Client\RequestException HTTPエラー時
     */
    private function get(string $path, array $query, string $accessToken, array $config): array
    {
        $queryString = $this->canonicalQuery($query);
        $url = rtrim($config['endpoint'], '/').$path.($queryString !== '' ? '?'.$queryString : '');
        $response = Http::withHeaders(['x-amz-access-token' => $accessToken])->get($url);
        $response->throw();

        $data = $response->json();
        if (!is_array($data)) {
            throw new RuntimeException('Amazon SP-API returned an invalid response.');
        }

        return $data;
    }

    /**
     * クエリパラメーターをキー順に並べ、URLエンコードしたクエリ文字列に変換する。
     *
     * @param array<string, scalar> $query クエリパラメーター
     * @return string URLに付加するクエリ文字列
     */
    private function canonicalQuery(array $query): string
    {
        ksort($query);
        $pairs = [];
        foreach ($query as $key => $value) {
            $values = is_array($value) ? $value : [$value];
            foreach ($values as $item) {
                $pairs[] = rawurlencode((string) $key).'='.rawurlencode((string) $item);
            }
        }

        return implode('&', $pairs);
    }
}
