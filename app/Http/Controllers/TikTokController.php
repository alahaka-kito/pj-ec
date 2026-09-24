<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\TikTokOrderService;

class TikTokController extends Controller
{
    /**
     * 1. 既存セラーをTikTok認可画面へリダイレクト
     */
    public function redirectToAuth()
    {
        $serviceId = config('services.tiktok.service_id');

        $authUrl = 'https://services.tiktokshop.com/open/authorize?' . http_build_query([
            'service_id' => $serviceId,
            'state'      => csrf_token(),
        ]);

        return redirect($authUrl);
    }

    /**
     * 2. 認可後のコールバック処理 (access_token および shop_cipher の取得・DB保存)
     */
    public function handleCallback(Request $request)
    {
        $code = $request->input('code');

        if (!$code) {
            return response()->json(['error' => 'Authorization code not found'], 400);
        }

        $appKey    = config('services.tiktok.app_key');
        $appSecret = config('services.tiktok.app_secret');

        try {
            // 1. トークン取得 API（auth.tiktok-shops.com を指定）
            $response = Http::get('https://auth.tiktok-shops.com/api/v2/token/get', [
                'app_key'    => $appKey,
                'app_secret' => $appSecret,
                'auth_code'  => $code,
                'grant_type' => 'authorized_code',
            ]);

            $data = $response->json();
            Log::info('TikTok Auth Response:', $data ?? []);

            if (isset($data['data']['access_token'])) {
                $tokenData   = $data['data'];
                $accessToken = $tokenData['access_token'];

                // 2. ショップ一覧から shop_cipher を取得（open-api.tiktokglobalshop.com を指定）
                $shopCipher = $this->fetchShopCipher($appKey, $appSecret, $accessToken);

                // 有効期限の変換処理
                $accessExpireIn  = $tokenData['access_token_expire_in'] ?? 86400;
                $refreshExpireIn = $tokenData['refresh_token_expire_in'] ?? 2592000;

                $accessExpireAt  = $accessExpireIn > 1000000000 
                    ? date('Y-m-d H:i:s', $accessExpireIn) 
                    : date('Y-m-d H:i:s', time() + $accessExpireIn);

                $refreshExpireAt = $refreshExpireIn > 1000000000 
                    ? date('Y-m-d H:i:s', $refreshExpireIn) 
                    : date('Y-m-d H:i:s', time() + $refreshExpireIn);

                // DBへトークン情報および shop_cipher を保存 / 更新 (UPSERT)
                DB::table('tiktok_tokens')->updateOrInsert(
                    ['shop_id' => $tokenData['seller_id'] ?? ($tokenData['open_id'] ?? 'default')],
                    [
                        'shop_cipher'             => $shopCipher,
                        'seller_name'             => $tokenData['seller_name'] ?? null,
                        'access_token'            => $accessToken,
                        'access_token_expire_at'  => $accessExpireAt,
                        'refresh_token'           => $tokenData['refresh_token'] ?? '',
                        'refresh_token_expire_at' => $refreshExpireAt,
                        'updated_at'              => now(),
                    ]
                );

                return response()->json([
                    'status'  => 'success',
                    'message' => '認証成功！アクセストークンおよび shop_cipher を正常に保存しました。',
                    'data'    => [
                        'seller_name'            => $tokenData['seller_name'] ?? '',
                        'shop_cipher'            => $shopCipher,
                        'access_token_expire_at' => $accessExpireAt,
                    ]
                ], 200, [], JSON_UNESCAPED_UNICODE);
            }

            Log::error('TikTok Auth Callback Error', ['response' => $data]);
            return response()->json(['error' => 'Failed to get access token', 'details' => $data], 500);

        } catch (\Exception $e) {
            Log::error('TikTok Connection Exception: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 認可されたショップ一覧を取得し、shop_cipher を返す
     */
    private function fetchShopCipher(string $appKey, string $appSecret, string $accessToken): ?string
    {
        $path        = '/authorization/202309/shops';
        $timestamp   = time();
        $queryParams = [
            'app_key'   => $appKey,
            'timestamp' => $timestamp,
        ];

        // 署名（sign）の計算
        ksort($queryParams);
        $paramString = '';
        foreach ($queryParams as $k => $v) {
            $paramString .= $k . $v;
        }
        $stringToSign = $appSecret . $path . $paramString . $appSecret;
        $sign         = hash_hmac('sha256', $stringToSign, $appSecret);

        $queryParams['sign'] = $sign;

        $response = Http::withHeaders([
            'x-tts-access-token' => $accessToken,
            'Content-Type'       => 'application/json',
        ])->get('https://open-api.tiktokglobalshop.com' . $path, $queryParams);

        $resData = $response->json();
        Log::info('TikTok Shops API Response:', ['body' => $resData]);

        if (isset($resData['data']['shops']) && is_array($resData['data']['shops'])) {
            foreach ($resData['data']['shops'] as $shop) {
                if (!empty($shop['cipher'])) {
                    return $shop['cipher'];
                }
                if (!empty($shop['shop_cipher'])) {
                    return $shop['shop_cipher'];
                }
            }
        }

        return null;
    }

    /**
     * 3. 注文一覧データの取得
     */
    public function getOrders(TikTokOrderService $service)
    {
        try {
            $orders = $service->fetchFormattedOrders();
            return response()->json($orders, 200, [], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}