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
     * 出品中のAmazon商品情報をSP-APIから全ページ取得する。
     *
     * @return array<int, array<string, mixed>> 出品SKUごとの商品情報
     */
    public function fetchAllListings(): array
    {
        $config = config('services.amazon');
        foreach (['lwa_client_id', 'lwa_client_secret', 'refresh_token', 'marketplace_id', 'seller_id', 'endpoint'] as $key) {
            if (empty($config[$key])) {
                throw new RuntimeException("Amazon SP-API configuration is missing: services.amazon.{$key}");
            }
        }

        $accessToken = $this->getAccessToken($config);
        $listings = [];
        $pageToken = null;
        do {
            $query = [
                'marketplaceIds' => [$config['marketplace_id']],
                'includedData' => ['summaries', 'attributes', 'offers', 'fulfillmentAvailability', 'relationships'],
                'pageSize' => 20,
            ];
            if ($pageToken !== null) {
                $query['pageToken'] = $pageToken;
            }

            $data = $this->get(
                '/listings/2021-08-01/items/' . rawurlencode($config['seller_id']),
                $query,
                $accessToken,
                $config
            );
            $items = $data['items'] ?? null;
            if (!is_array($items)) {
                throw new RuntimeException('Amazon SP-API returned an invalid listings response.');
            }
            array_push($listings, ...$items);
            $pageToken = $data['pagination']['nextToken'] ?? null;
        } while (is_string($pageToken) && $pageToken !== '');

        return $listings;
    }

    /**
     * Product Type Definitions APIから配送テンプレートIDと表示名の対応を取得する。
     *
     * @param string $productType Amazon商品タイプ
     * @return array<string, string> 配送テンプレートID => Seller Central上の表示名
     */
    public function fetchMerchantShippingTemplateNames(string $productType): array
    {
        $config = config('services.amazon');
        foreach (['lwa_client_id', 'lwa_client_secret', 'refresh_token', 'marketplace_id', 'seller_id', 'endpoint'] as $key) {
            if (empty($config[$key])) {
                throw new RuntimeException("Amazon SP-API configuration is missing: services.amazon.{$key}");
            }
        }

        $accessToken = $this->getAccessToken($config);
        $definition = $this->get(
            '/definitions/2020-09-01/productTypes/' . rawurlencode($productType),
            [
                'marketplaceIds' => [$config['marketplace_id']],
                'sellerId' => $config['seller_id'],
                'requirements' => 'LISTING',
                'requirementsEnforced' => 'NOT_ENFORCED',
                'locale' => 'ja_JP',
            ],
            $accessToken,
            $config
        );

        $schemaUrl = $definition['schema']['link']['resource'] ?? null;
        if (!is_string($schemaUrl) || $schemaUrl === '') {
            return [];
        }

        $schemaResponse = Http::timeout(30)->get($schemaUrl);
        $schemaResponse->throw();
        $schema = $schemaResponse->json();
        if (!is_array($schema)) {
            return [];
        }

        $shippingGroup = $this->findShippingGroupDefinition($schema);
        $enum = $shippingGroup['enum'] ?? [];
        $enumNames = $shippingGroup['enumNames'] ?? [];
        if (!is_array($enum) || !is_array($enumNames)) {
            return [];
        }

        $templates = [];
        foreach ($enum as $index => $templateId) {
            $templateName = $enumNames[$index] ?? null;
            if (is_string($templateId) && is_string($templateName)) {
                $templates[$templateId] = $templateName;
            }
        }

        return $templates;
    }

    /**
     * 商品タイプ定義のJSON Schemaからmerchant_shipping_groupの値定義を探す。
     *
     * @param array<string, mixed> $schema Amazon商品タイプJSON Schema
     * @return array<string, mixed> 配送テンプレートのenumとenumNames
     */
    private function findShippingGroupDefinition(array $schema): array
    {
        foreach ($schema as $key => $value) {
            if ($key === 'merchant_shipping_group' && is_array($value)) {
                $valueDefinition = $value['items']['properties']['value'] ?? [];
                if (is_array($valueDefinition) && isset($valueDefinition['enum'], $valueDefinition['enumNames'])) {
                    return $valueDefinition;
                }
            }
            if (is_array($value)) {
                $definition = $this->findShippingGroupDefinition($value);
                if ($definition !== []) {
                    return $definition;
                }
            }
        }

        return [];
    }

    /**
     * FBM出品の在庫数を出品SKUごとに取得する。
     * Listings Items APIの検索上限に合わせてSKUを20件ずつ照合する。
     *
     * @param array<int, string> $sellerSkus Amazonの出品SKU
     * @return array<int, array<string, mixed>> 在庫サマリー形式に整えたSKU別在庫
     */
    public function fetchSellerFulfilledInventories(array $sellerSkus): array
    {
        if ($sellerSkus === []) {
            return [];
        }

        $config = config('services.amazon');
        foreach (['lwa_client_id', 'lwa_client_secret', 'refresh_token', 'marketplace_id', 'seller_id', 'endpoint'] as $key) {
            if (empty($config[$key])) {
                throw new RuntimeException("Amazon SP-API configuration is missing: services.amazon.{$key} (AMAZON_SELLER_IDを確認してください)");
            }
        }

        $accessToken = $this->getAccessToken($config);
        $inventories = [];
        foreach (array_chunk(array_values(array_unique($sellerSkus)), 20) as $skuBatch) {
            $data = $this->get('/listings/2021-08-01/items/'.rawurlencode($config['seller_id']), [
                'marketplaceIds' => [$config['marketplace_id']],
                'identifiers' => $skuBatch,
                'identifiersType' => 'SKU',
                'includedData' => ['fulfillmentAvailability', 'summaries'],
                'pageSize' => 20,
            ], $accessToken, $config);

            foreach (($data['items'] ?? []) as $item) {
                $sku = (string) ($item['sku'] ?? '');
                if ($sku === '') {
                    continue;
                }
                foreach (($item['fulfillmentAvailability'] ?? []) as $availability) {
                    if (($availability['fulfillmentChannelCode'] ?? null) !== 'DEFAULT') {
                        continue;
                    }
                    if (is_numeric($availability['quantity'] ?? null)) {
                        $inventories[] = [
                            'sellerSku' => $sku,
                            'productType' => $item['summaries'][0]['productType'] ?? 'PRODUCT',
                            'inventoryDetails' => ['fulfillableQuantity' => (int) $availability['quantity']],
                        ];
                        break;
                    }
                }
            }
        }

        return $inventories;
    }

    /**
     * AmazonのFBM出品SKUに在庫数を送信する。
     * Listings Items APIのPATCH結果を返し、APIエラー時は例外を送出する。
     *
     * @param string $sellerSku Amazon出品SKU
     * @param string $productType Amazon商品タイプ
     * @param int $quantity 設定する在庫数
     * @return array<string, mixed> Amazonから返された更新結果
     */
    public function updateSellerFulfilledInventory(string $sellerSku, string $productType, int $quantity): array
    {
        $config = config('services.amazon');
        foreach (['lwa_client_id', 'lwa_client_secret', 'refresh_token', 'marketplace_id', 'seller_id', 'endpoint'] as $key) {
            if (empty($config[$key])) {
                throw new RuntimeException("Amazon SP-API configuration is missing: services.amazon.{$key}");
            }
        }

        $accessToken = $this->getAccessToken($config);
        $path = '/listings/2021-08-01/items/'.rawurlencode($config['seller_id']).'/'.rawurlencode($sellerSku);
        $queryString = $this->canonicalQuery(['marketplaceIds' => [$config['marketplace_id']]]);
        $url = rtrim($config['endpoint'], '/').$path.'?'.$queryString;
        $response = Http::withHeaders([
            'x-amz-access-token' => $accessToken,
            'Content-Type' => 'application/json',
        ])->patch($url, [
            'productType' => $productType !== '' ? $productType : 'PRODUCT',
            'patches' => [[
                'op' => 'replace',
                'path' => '/attributes/fulfillment_availability',
                'value' => [[
                    'fulfillment_channel_code' => 'DEFAULT',
                    'quantity' => max(0, $quantity),
                ]],
            ]],
        ]);
        $response->throw();
        $data = $response->json();

        return is_array($data) ? $data : [];
    }

    /**
     * AmazonのFBA在庫サマリーをSKU指定で取得する。
     * APIの上限に合わせてSKUを50件ずつ問い合わせ、ページング結果をまとめて返す。
     *
     * @param array<int, string> $sellerSkus 取得対象のAmazon SKU
     * @return array<int, array<string, mixed>> SKUごとの在庫サマリー
     */
    public function fetchInventorySummaries(array $sellerSkus): array
    {
        if ($sellerSkus === []) {
            return [];
        }

        $config = config('services.amazon');
        foreach (['lwa_client_id', 'lwa_client_secret', 'refresh_token', 'marketplace_id', 'endpoint'] as $key) {
            if (empty($config[$key])) {
                throw new RuntimeException("Amazon SP-API configuration is missing: services.amazon.{$key}");
            }
        }

        $accessToken = $this->getAccessToken($config);
        $summaries = [];

        foreach (array_chunk(array_values(array_unique($sellerSkus)), 50) as $skuBatch) {
            $nextToken = null;
            do {
                $query = [
                    'details' => 'true',
                    'granularityType' => 'Marketplace',
                    'granularityId' => $config['marketplace_id'],
                    'marketplaceIds' => [$config['marketplace_id']],
                    'sellerSkus' => $skuBatch,
                ];
                if ($nextToken !== null) {
                    $query['nextToken'] = $nextToken;
                }

                $data = $this->get('/fba/inventory/v1/summaries', $query, $accessToken, $config);
                $pageSummaries = $data['payload']['inventorySummaries'] ?? null;
                if (!is_array($pageSummaries)) {
                    throw new RuntimeException('Amazon SP-API returned invalid inventory summaries.');
                }

                array_push($summaries, ...$pageSummaries);
                $nextToken = $data['pagination']['nextToken'] ?? null;
            } while (is_string($nextToken) && $nextToken !== '');
        }

        return $summaries;
    }

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
            $encodedValue = is_array($value)
                ? implode(',', array_map(static fn ($item) => (string) $item, $value))
                : (string) $value;
            $pairs[] = rawurlencode((string) $key).'='.rawurlencode($encodedValue);
        }

        return implode('&', $pairs);
    }
}
