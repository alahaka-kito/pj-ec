<?php

namespace App\Services\CsvTransformers;

/**
 * Amazon注文レポート（amazon.txt）データ変換クラス
 */
class AmazonTransformer extends AbstractTransformer
{
    /**
     * AmazonのTXTデータをヤマト産直CSVフォーマットへ変換
     *
     * @param array $dataRows 入力データ行配列
     * @param array $header ヘッダー行配列
     * @param object|null $dbData DB固定値データ
     * @param array $yamatoIdxMap ヤマト列マッピングマップ
     * @param int $maxColumns 出力列数
     * @param mixed $hatsuMaster 発荷主マスタデータ
     * @param int $hatsuIdx 発荷主コード出力先インデックス
     * @return array 変換結果
     */
    public function transform(
        array $dataRows, 
        array $header, 
        $dbData, 
        array $yamatoIdxMap, 
        int $maxColumns, 
        $hatsuMaster, 
        int $hatsuIdx
    ): array {
        // Amazonテキストヘッダーマッピング
        $amazonMapping = [
            'order-id'           => 'order-id',
            'purchase-date'      => 'purchase-date',
            'quantity-purchased' => 'quantity-purchased',
            'product-name'       => 'product-name',
            'ship-postal-code'   => 'ship-postal-code',
            'buyer-phone-number' => 'buyer-phone-number',
            'ship-state'         => 'ship-state',
            'ship-address-1'     => 'ship-address-1',
            'ship-address-2'     => 'ship-address-2',
            'ship-address-3'     => 'ship-address-3',
            'recipient-name'     => 'recipient-name',
            'sku'                => 'sku',
        ];

        $amazonIdx = [];
        foreach ($amazonMapping as $key => $headerName) {
            $amazonIdx[$key] = false;
            foreach ($header as $k => $v) {
                $utf8Value = mb_convert_encoding($v, 'UTF-8', 'UTF-8,CP932,SJIS,EUC-JP');
                $cleanHeaderValue = trim(str_replace(["\t", "\r", "\n", " "], "", $utf8Value));
                if ($cleanHeaderValue === $headerName) {
                    $amazonIdx[$key] = $k;
                    break;
                }
            }
        }

        $results = [];

        foreach ($dataRows as $row) {
            if ($amazonIdx['order-id'] === false || !isset($row[$amazonIdx['order-id']])) continue;

            $forcedMaxColumns = max($maxColumns, 95);
            $newRow = array_fill(0, $forcedMaxColumns, null);

            // 共通: DB固定値の割り当て
            $this->applyDbDefaults($newRow, $dbData, $yamatoIdxMap);

            // 共通: 商品名マッチングによる発荷主コード判定
            $prodNameIdx = $amazonIdx['product-name'];
            $amazonProductName = ($prodNameIdx !== false && isset($row[$prodNameIdx])) ? $row[$prodNameIdx] : '';
            $this->matchHatsuNinushiCode($newRow, $amazonProductName, $hatsuMaster);

            // A列: 運送依頼番号 <= order-id
            if ($amazonIdx['order-id'] !== false && isset($row[$amazonIdx['order-id']])) {
                $newRow[0] = $row[$amazonIdx['order-id']];
            }

            // I列: 真荷主データ作成日 <= purchase-date (yyyy/mm/ddに整形)
            if ($amazonIdx['purchase-date'] !== false && !empty($row[$amazonIdx['purchase-date']])) {
                $rawDate = $row[$amazonIdx['purchase-date']];
                $timestamp = strtotime($rawDate);
                $newRow[8] = ($timestamp !== false) ? date('Y/m/d', $timestamp) : $rawDate;
            }

            // AF列・CQ列: 数量 <= quantity-purchased
            if ($amazonIdx['quantity-purchased'] !== false && isset($row[$amazonIdx['quantity-purchased']])) {
                $qty = $row[$amazonIdx['quantity-purchased']];
                $newRow[31] = $qty; // AF列
                $newRow[94] = $qty; // CQ列
            }

            // AK列・CO列: 送り状標記用品名1 / 商品名称 <= product-name
            if ($prodNameIdx !== false && isset($row[$prodNameIdx])) {
                $newRow[36] = $row[$prodNameIdx]; // AK列
                $newRow[92] = $row[$prodNameIdx]; // CO列
            }

            // M列: 荷届先郵便番号 <= ship-postal-code (ハイフンを除外)
            if ($amazonIdx['ship-postal-code'] !== false && isset($row[$amazonIdx['ship-postal-code']])) {
                $postalCode = str_replace('-', '', $row[$amazonIdx['ship-postal-code']]);
                $newRow[12] = $postalCode;
            }

            // N列: 荷届先電話番号 <= buyer-phone-number
            if ($amazonIdx['buyer-phone-number'] !== false && !empty($row[$amazonIdx['buyer-phone-number']])) {
                $newRow[13] = str_replace('(+81)', '', $row[$amazonIdx['buyer-phone-number']]);
            }

            // O列: 荷届先住所１ <= ship-state + ship-address-1 + ship-address-2
            $address1 = '';
            if ($amazonIdx['ship-state'] !== false) $address1 .= $row[$amazonIdx['ship-state']] ?? '';
            if ($amazonIdx['ship-address-1'] !== false) $address1 .= $row[$amazonIdx['ship-address-1']] ?? '';
            if ($amazonIdx['ship-address-2'] !== false) $address1 .= $row[$amazonIdx['ship-address-2']] ?? '';
            if ($address1 !== '') $newRow[14] = $address1;

            // P列: 荷届先住所２ <= ship-address-3
            if ($amazonIdx['ship-address-3'] !== false && isset($row[$amazonIdx['ship-address-3']])) {
                $newRow[15] = $row[$amazonIdx['ship-address-3']];
            }

            // Q列: 荷届先名 <= recipient-name
            if ($amazonIdx['recipient-name'] !== false && isset($row[$amazonIdx['recipient-name']])) {
                $newRow[16] = $row[$amazonIdx['recipient-name']];
            }

            // CN列: 商品コード <= sku
            if ($amazonIdx['sku'] !== false && isset($row[$amazonIdx['sku']])) {
                $newRow[91] = $row[$amazonIdx['sku']];
            }

            $results[] = $newRow;
        }

        return $results;
    }
}