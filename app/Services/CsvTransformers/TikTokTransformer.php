<?php

namespace App\Services\CsvTransformers;

/**
 * TikTok Shop注文データ変換クラス
 */
class TikTokTransformer extends AbstractTransformer
{
    /**
     * TikTokのCSVデータをヤマト産直CSVフォーマットへ変換
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
        $tiktokMapping = [
            '注文ID'       => '注文ID',
            '注文作成時刻' => '注文作成時刻',
            '数量'         => '数量',
            '商品名'       => '商品名',
            '郵便番号'     => '郵便番号',
            '電話番号'     => '電話番号',
            '受取人'       => '受取人',
            'SKU ID'       => 'SKUID',
        ];

        $tiktokIdx = [];
        foreach ($tiktokMapping as $key => $headerName) {
            $tiktokIdx[$key] = false;
            foreach ($header as $k => $v) {
                $utf8Value = mb_convert_encoding($v, 'UTF-8', 'UTF-8,CP932,SJIS,EUC-JP');
                $cleanHeaderValue = trim(str_replace(["\t", "\r", "\n", " "], "", $utf8Value));
                if ($cleanHeaderValue === $headerName) {
                    $tiktokIdx[$key] = $k;
                    break;
                }
            }
        }

        $addressKeys = ['都道府県', '市区町村', '町名', '詳細住所1', '詳細住所2'];
        foreach ($addressKeys as $key) {
            $tiktokIdx[$key] = false;
            foreach ($header as $k => $v) {
                $utf8Value = mb_convert_encoding($v, 'UTF-8', 'UTF-8,CP932,SJIS,EUC-JP');
                $cleanHeaderValue = trim(str_replace(["\t", "\r", "\n", " "], "", $utf8Value));
                if (str_contains($cleanHeaderValue, $key)) {
                    $tiktokIdx[$key] = $k;
                    break;
                }
            }
        }

        $results = [];

        foreach ($dataRows as $row) {
            if ($tiktokIdx['注文ID'] === false || !isset($row[$tiktokIdx['注文ID']])) continue;

            $forcedMaxColumns = max($maxColumns, 95);
            $newRow = array_fill(0, $forcedMaxColumns, null);

            // 共通: DB固定値の割り当て
            $this->applyDbDefaults($newRow, $dbData, $yamatoIdxMap);

            // 共通: 商品名マッチングによる発荷主コード判定
            $targetProdIdx = ($tiktokIdx['商品名'] !== false) ? $tiktokIdx['商品名'] : 36;
            $tiktokProductName = (isset($row[$targetProdIdx])) ? $row[$targetProdIdx] : '';
            $this->matchHatsuNinushiCode($newRow, $tiktokProductName, $hatsuMaster);

            // A列: 運送依頼番号
            if ($tiktokIdx['注文ID'] !== false && isset($row[$tiktokIdx['注文ID']])) {
                $newRow[0] = $row[$tiktokIdx['注文ID']];
            }
            
            // I列: 真荷主データ作成日
            if ($tiktokIdx['注文作成時刻'] !== false && !empty($row[$tiktokIdx['注文作成時刻']])) {
                $datetimeStr = explode(' ', $row[$tiktokIdx['注文作成時刻']])[0];
                $timestamp = strtotime($datetimeStr);
                $newRow[8] = ($timestamp !== false) ? date('Y/m/d', $timestamp) : $datetimeStr;
            }
            
            // AF列・CQ列: 数量
            if ($tiktokIdx['数量'] !== false && isset($row[$tiktokIdx['数量']])) {
                $newRow[31] = $row[$tiktokIdx['数量']];
                $newRow[94] = $row[$tiktokIdx['数量']];
            }
            // AK列・CO列: 商品名
            if (isset($row[$targetProdIdx])) {
                $newRow[36] = $row[$targetProdIdx];
                $newRow[92] = $row[$targetProdIdx];
            }
            // M列: 郵便番号 (ハイフンを除外)
            if ($tiktokIdx['郵便番号'] !== false && isset($row[$tiktokIdx['郵便番号']])) {
                $postalCode = str_replace('-', '', $row[$tiktokIdx['郵便番号']]);
                $newRow[12] = $postalCode;
            }
            
            // N列: 電話番号
            if ($tiktokIdx['電話番号'] !== false && !empty($row[$tiktokIdx['電話番号']])) {
                $newRow[13] = str_replace('(+81)', '', $row[$tiktokIdx['電話番号']]);
            }
            
            // Q列: 荷届先名
            if ($tiktokIdx['受取人'] !== false && isset($row[$tiktokIdx['受取人']])) {
                $newRow[16] = $row[$tiktokIdx['受取人']];
            }
            // CN列: 商品コード
            if ($tiktokIdx['SKU ID'] !== false && isset($row[$tiktokIdx['SKU ID']])) {
                $newRow[91] = $row[$tiktokIdx['SKU ID']];
            }

            // O列: 荷届先住所１
            $address1 = '';
            if ($tiktokIdx['都道府県'] !== false) $address1 .= $row[$tiktokIdx['都道府県']] ?? '';
            if ($tiktokIdx['市区町村'] !== false) $address1 .= $row[$tiktokIdx['市区町村']] ?? '';
            if ($tiktokIdx['町名'] !== false)     $address1 .= $row[$tiktokIdx['町名']] ?? '';
            if ($tiktokIdx['詳細住所1'] !== false) $address1 .= $row[$tiktokIdx['詳細住所1']] ?? '';
            if ($address1 !== '') $newRow[14] = $address1;

            // P列: 荷届先住所２
            $address2 = '';
            if ($tiktokIdx['詳細住所2'] !== false) $address2 .= $row[$tiktokIdx['詳細住所2']] ?? '';
            if ($address2 !== '') $newRow[15] = $address2;

            $results[] = $newRow;
        }

        return $results;
    }
}