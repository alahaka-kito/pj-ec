<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class TikTokOrderService
{
    /**
     * 注文一覧を取得して整形・返却する
     */
    public function fetchFormattedOrders(int $days = 1)
    {
        // 1. DBから最新のトークン情報を取得
        $tokenRow = DB::table('tiktok_tokens')->latest('updated_at')->first();

        if (!$tokenRow || empty($tokenRow->access_token)) {
            throw new \Exception('有効なアクセストークンがデータベースに見つかりません。先に /tiktok/auth で認証を行ってください。');
        }

        // 2. トークンの有効期限チェック & 必要なら自動更新
        $tokenRow = $this->ensureValidAccessToken($tokenRow);

        $accessToken = $tokenRow->access_token;
        $shopCipher  = $tokenRow->shop_cipher;

        $appKey    = config('services.tiktok.app_key');
        $appSecret = config('services.tiktok.app_secret');

        $path      = '/order/202309/orders/search';
        $timestamp = time();

        // クエリパラメータの構築
        $queryParams = [
            'app_key'     => $appKey,
            'shop_cipher' => $shopCipher,
            'timestamp'   => $timestamp,
            'page_size'   => 100,
        ];

        // 署名（sign）の計算
        ksort($queryParams);
        $paramString = '';
        foreach ($queryParams as $k => $v) {
            $paramString .= $k . $v;
        }

        // 過去90日間の注文を対象に指定
        $postBody = [
            'create_time_ge' => strtotime("-{$days} days"),
            'create_time_le' => time(),
        ];

        $jsonBody     = json_encode($postBody);
        $stringToSign = $appSecret . $path . $paramString . $jsonBody . $appSecret;
        $sign         = hash_hmac('sha256', $stringToSign, $appSecret);

        $queryParams['sign'] = $sign;

        $url = 'https://open-api.tiktokglobalshop.com' . $path . '?' . http_build_query($queryParams);

        $response = Http::withHeaders([
            'x-tts-access-token' => $accessToken,
            'Content-Type'       => 'application/json',
        ])->withBody($jsonBody, 'application/json')->post($url);

        $resData = $response->json();

        Log::info('TikTok Search Orders Response:', ['body' => $resData]);

        if (isset($resData['data']['orders']) && is_array($resData['data']['orders'])) {
            return $resData['data']['orders'];
        }

        // 注文が0件の場合、TikTok APIはordersを省略してtotal_countだけ返すことがある。
        if (
            isset($resData['code'], $resData['data']['total_count'])
            && (int) $resData['code'] === 0
            && (int) $resData['data']['total_count'] === 0
        ) {
            return [];
        }

        return [
            'message'          => '注文データが見つかりませんでした。',
            'used_shop_cipher' => $shopCipher,
            'raw_response'     => $resData,
        ];
    }

    /**
     * API呼び出し前にアクセストークンの有効期限を確認し、必要なら更新する。
     *
     * @param object $tokenRow tiktok_tokensテーブルから取得したトークン行
     * @return object 有効なアクセストークンを持つトークン行
     */
    public function ensureValidAccessToken($tokenRow)
    {
        $expireAt = strtotime($tokenRow->access_token_expire_at ?? '1970-01-01');

        // 以前の期限計算ミスで極端な未来日になったデータは、期限切れとして更新を試みる。
        if ($expireAt > time() + (5 * 365 * 24 * 60 * 60)) {
            Log::warning('保存されているTikTokアクセストークン期限が不正な未来日です。トークンを更新します。', [
                'access_token_expire_at' => $tokenRow->access_token_expire_at,
            ]);
            $expireAt = 0;
        }

        // 有効期限が残り5分未満の場合、またはすでに切れている場合に自動更新を実行
        if (time() >= ($expireAt - 300)) {
            Log::info('TikTok access_token matched refresh threshold. Refreshing token...');

            $appKey    = config('services.tiktok.app_key');
            $appSecret = config('services.tiktok.app_secret');

            $response = Http::get('https://auth.tiktok-shops.com/api/v2/token/refresh', [
                'app_key'       => $appKey,
                'app_secret'    => $appSecret,
                'refresh_token' => $tokenRow->refresh_token,
                'grant_type'    => 'refresh_token',
            ]);

            $data = $response->json();
            Log::info('TikTok Refresh Token Response:', $data ?? []);

            if (isset($data['data']['access_token'])) {
                $newTokenData = $data['data'];

                $accessExpireAt = $this->toExpiryDateTime(
                    $newTokenData['access_token_expire_in'] ?? null,
                    86400
                );
                $refreshExpireAt = $this->toExpiryDateTime(
                    $newTokenData['refresh_token_expire_in'] ?? null,
                    2592000
                );

                // DB内のトークン情報を最新に更新
                DB::table('tiktok_tokens')
                    ->where('id', $tokenRow->id)
                    ->update([
                        'access_token'            => $newTokenData['access_token'],
                        'access_token_expire_at'  => $accessExpireAt,
                        'refresh_token'           => $newTokenData['refresh_token'] ?? $tokenRow->refresh_token,
                        'refresh_token_expire_at' => $refreshExpireAt,
                        'updated_at'              => now(),
                    ]);

                // 更新後の最新データを取得して返す
                return DB::table('tiktok_tokens')->where('id', $tokenRow->id)->first();
            } else {
                Log::error('Failed to auto-refresh TikTok access token', ['response' => $data]);
                $message = $data['message'] ?? 'TikTokから更新後のアクセストークンが返されませんでした。';
                throw new \RuntimeException('TikTokアクセストークンの自動更新に失敗しました: '.$message);
            }
        }

        return $tokenRow;
    }

    /**
     * TikTokの期限値を日時文字列に変換する。Unix時刻ならそのまま、残り秒数なら現在時刻に加算する。
     */
    private function toExpiryDateTime($expirationValue, int $fallbackSeconds): string
    {
        $expiration = is_numeric($expirationValue) ? (int) $expirationValue : 0;

        if ($expiration > 1000000000) {
            return date('Y-m-d H:i:s', $expiration);
        }

        return date('Y-m-d H:i:s', time() + ($expiration > 0 ? $expiration : $fallbackSeconds));
    }
}
