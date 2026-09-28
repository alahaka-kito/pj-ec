<?php

namespace App\Services\CsvTransformers;

/**
 * 各モール共通の変換処理を提供する基底クラス
 */
abstract class AbstractTransformer implements CsvTransformerInterface
{
    /**
     * DB固定値を変換先の行データにセット
     *
     * @param array $newRow 変換先データ行（参照渡し）
     * @param object|null $dbData DB固定値オブジェクト
     * @param array $yamatoIdxMap ヤマト列マッピング情報
     */
    protected function applyDbDefaults(array &$newRow, $dbData, array $yamatoIdxMap): void
    {
        if (!$dbData) return;

        foreach ($yamatoIdxMap as $colName => $targetIdx) {
            if (isset($dbData->$colName) && $dbData->$colName !== '') {
                $value = $dbData->$colName;
                if ($colName === 'shin_ninushi_data_created_time' && !empty($value)) {
                    $timeTimestamp = strtotime($value);
                    if ($timeTimestamp !== false) {
                        $value = date('H:i', $timeTimestamp);
                    }
                }
                $newRow[$targetIdx] = $value;
            }
        }
    }

    /**
     * 商品名マッチングによる発荷主コードの自動判定・割り当て
     *
     * @param array $newRow 変換先データ行（参照渡し）
     * @param string $sourceProductName 元データの商品名
     * @param mixed $hatsuMaster 発荷主マスタデータ
     */
    protected function matchHatsuNinushiCode(array &$newRow, string $sourceProductName, $hatsuMaster): void
    {
        if (empty($sourceProductName) || !$hatsuMaster) return;

        $utf8Name = mb_convert_encoding($sourceProductName, 'UTF-8', 'UTF-8,CP932,SJIS,EUC-JP');
        $cleanSourceProductName = preg_replace('/[\s\x{3000}\x{00a0}]+/u', '', $utf8Name);

        $masterArray = [];
        if (method_exists($hatsuMaster, 'toArray')) {
            $masterArray = $hatsuMaster->toArray();
        } elseif (is_array($hatsuMaster)) {
            $masterArray = $hatsuMaster;
        } else {
            foreach ($hatsuMaster as $m) {
                $masterArray[] = $m;
            }
        }

        foreach ($masterArray as $master) {
            $masterProductName = is_array($master) ? ($master['product_name'] ?? '') : ($master->product_name ?? '');
            $hatsuNinushiCode = is_array($master) ? ($master['hatsu_ninushi_code'] ?? '') : ($master->hatsu_ninushi_code ?? '');

            if (!empty($masterProductName)) {
                $utf8MasterName = mb_convert_encoding($masterProductName, 'UTF-8', 'UTF-8,CP932,SJIS,EUC-JP');
                $cleanMasterName = preg_replace('/[\s\x{3000}\x{00a0}]+/u', '', $utf8MasterName);

                if ($cleanMasterName !== '' && str_contains($cleanSourceProductName, $cleanMasterName)) {
                    $newRow[10] = $hatsuNinushiCode;
                    break;
                }
            }
        }
    }
}