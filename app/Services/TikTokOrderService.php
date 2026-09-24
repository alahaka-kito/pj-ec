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
    public function fetchFormattedOrders()
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
            'create_time_ge' => strtotime('-90 days'),
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

        if (isset($resData['data']['orders']) && !empty($resData['data']['orders'])) {
            return $resData['data']['orders'];
        }

        return [
            'message'          => '注文データが見つかりませんでした。',
            'used_shop_cipher' => $shopCipher,
            'raw_response'     => $resData,
        ];
    }

    /**
     * トークンの有効期限をチェックし、切れている場合は refresh_token で自動更新する
     */
    private function ensureValidAccessToken($tokenRow)
    {
        $expireAt = strtotime($tokenRow->access_token_expire_at ?? '1970-01-01');

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

                $accessExpireIn  = $newTokenData['access_token_expire_in'] ?? 86400;
                $refreshExpireIn = $newTokenData['refresh_token_expire_in'] ?? 2592000;

                $accessExpireAt  = date('Y-m-d H:i:s', time() + $accessExpireIn);
                $refreshExpireAt = date('Y-m-d H:i:s', time() + $refreshExpireIn);

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
            }
        }

        return $tokenRow;
    }
}