<?php

namespace App\Services\CsvTransformers;

class TikTokTransformer
{
    /**
     * TikTok固有のデータ変換ロジック
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
        // TikTok用のマッピング項目定義（現状のCSVヘッダー名と完全一致で探す設定）
        $tiktokMapping = [
            '注文ID'       => '注文ID',
            '注文作成時刻' => '注文作成時刻',
            '数量'         => '数量',
            '商品名'       => '商品名',
            '郵便番号'     => '郵便番号',
            '電話番号'     => '電話番号',
            '受取人'       => '受取人',
            'SKU ID'       => 'SKU ID',
        ];

        // 文字コードをUTF-8にクリーニングしながら正確に列を特定する
        $tiktokIdx = [];
        foreach ($tiktokMapping as $key => $headerName) {
            $tiktokIdx[$key] = false;
            foreach ($header as $k => $v) {
                // 文字コードをUTF-8に変換し、不要な文字を除去
                $utf8Value = mb_convert_encoding($v, 'UTF-8', 'UTF-8,CP932,SJIS,EUC-JP');
                $cleanHeaderValue = trim(str_replace(["\t", "\r", "\n", " ", " "], "", $utf8Value));
                
                // CSV側のヘッダー名と設定値が完全に一致するか判定
                if ($cleanHeaderValue === $headerName) {
                    $tiktokIdx[$key] = $k;
                    break;
                }
            }
        }
        
        // 住所関連の列も同様に特定
        $addressKeys = ['都道府県', '市区町村', '町名', '詳細住所1', '詳細住所2'];
        foreach ($addressKeys as $key) {
            $tiktokIdx[$key] = false;
            foreach ($header as $k => $v) {
                $utf8Value = mb_convert_encoding($v, 'UTF-8', 'UTF-8,CP932,SJIS,EUC-JP');
                $cleanHeaderValue = trim(str_replace(["\t", "\r", "\n", " ", " "], "", $utf8Value));
                if (str_contains($cleanHeaderValue, $key)) {
                    $tiktokIdx[$key] = $k;
                    break;
                }
            }
        }

        // マスタデータを配列に変換
        $masterArray = [];
        if ($hatsuMaster !== null) {
            if (method_exists($hatsuMaster, 'toArray')) {
                $masterArray = $hatsuMaster->toArray();
            } elseif (is_array($hatsuMaster)) {
                $masterArray = $hatsuMaster;
            } else {
                foreach ($hatsuMaster as $m) {
                    $masterArray[] = $m;
                }
            }
        }

        $results = [];

        foreach ($dataRows as $row) {
            // 注文ID列が特定できない、または行データが足りない場合はスキップ
            if ($tiktokIdx['注文ID'] === false || !isset($row[$tiktokIdx['注文ID']])) continue;

            // コントローラーに依存せず、ヤマトの最大列数を確実に担保
            $forcedMaxColumns = max($maxColumns, 95); 
            $newRow = array_fill(0, $forcedMaxColumns, null);

            // DB固定値の割り当て
            if ($dbData) {
                foreach ($yamatoIdxMap as $colName => $targetIdx) {
                    if (isset($dbData->$colName) && $dbData->$colName !== '') {
                        $value = $dbData->$colName;
                        if ($colName === 'shin_ninushi_data_created_time' && !empty($value)) {
                            $timeTimestamp = strtotime($value);
                            if ($timeTimestamp !== false) $value = date('H:i', $timeTimestamp);
                        }
                        $newRow[$targetIdx] = $value;
                    }
                }
            }

            // DBデータの完全一致判定（スペース除外・DBの値をすべて含む条件）
            $targetProdIdx = ($tiktokIdx['商品名'] !== false) ? $tiktokIdx['商品名'] : 36;

            if (isset($row[$targetProdIdx]) && !empty($row[$targetProdIdx])) {
                $tiktokProductName = $row[$targetProdIdx];
                
                // 一旦UTF-8に確実に変換
                $utf8TikTokName = mb_convert_encoding($tiktokProductName, 'UTF-8', 'UTF-8,CP932,SJIS,EUC-JP');
                // 特殊な空白文字やスペース、改行を徹底的に消し去る
                $cleanTikTokName = preg_replace('/[\s\x{3000}\x{00a0}]+/u', '', $utf8TikTokName);

                foreach ($masterArray as $master) {
                    $masterProductName = is_array($master) ? ($master['product_name'] ?? '') : ($master->product_name ?? '');
                    $hatsuNinushiCode = is_array($master) ? ($master['hatsu_ninushi_code'] ?? '') : ($master->hatsu_ninushi_code ?? '');

                    if (!empty($masterProductName)) {
                        // DB側もUTF-8に変換してスペースを徹底排除
                        $utf8MasterName = mb_convert_encoding($masterProductName, 'UTF-8', 'UTF-8,CP932,SJIS,EUC-JP');
                        $cleanMasterName = preg_replace('/[\s\x{3000}\x{00a0}]+/u', '', $utf8MasterName);

                        // 判定基準: DB側の値（スペース除いた全て）が、TikTok側の商品名に含まれているか
                        if ($cleanMasterName !== '' && str_contains($cleanTikTokName, $cleanMasterName)) {
                            $newRow[10] = $hatsuNinushiCode;
                            break; 
                        }
                    }
                }
            }

            // A列: 運送依頼番号 <= 注文ID
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
            // M列: 郵便番号
            if ($tiktokIdx['郵便番号'] !== false && isset($row[$tiktokIdx['郵便番号']])) {
                $newRow[12] = $row[$tiktokIdx['郵便番号']];
            }
            
            // N列: 電話番号
            if ($tiktokIdx['電話番号'] !== false && !empty($row[$tiktokIdx['電話番号']])) {
                $newRow[13] = str_replace('(+81)', '', $row[$tiktokIdx['電話番号']]);
            }
            
            // Q列: 荷届先名
            if ($tiktokIdx['受取人'] !== false && isset($row[$tiktokIdx['受取人']])) {
                $newRow[16] = $row[$tiktokIdx['受取人']];
            }
            // CN列: 商品コード <= SKU ID
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

            // クォーテーション囲み処理
            $quotedRow = array_map(function($value) {
                if ($value === null || $value === '') return null;
                $valueStr = (string)$value;
                if (str_starts_with($valueStr, '"') && str_ends_with($valueStr, '"')) return $valueStr;
                return '"' . $valueStr . '"';
            }, $newRow);

            $results[] = $quotedRow;
        }

        return $results;
    }
}