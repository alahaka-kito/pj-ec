<?php

namespace App\Services\CsvTransformers;

/**
 * CSV / TXT 変換トランスフォーマー用インターフェース
 */
interface CsvTransformerInterface
{
    /**
     * 各モールのデータをヤマト産直CSVフォーマットへ変換
     *
     * @param array $dataRows 入力データ行配列
     * @param array $header ヘッダー行配列
     * @param object|null $dbData DBから取得したヤマト固定値データ
     * @param array $yamatoIdxMap ヤマトCSVの列インデックスマップ
     * @param int $maxColumns 最大出力列数
     * @param mixed $hatsuMaster 発荷主マスタデータ
     * @param int $hatsuIdx 発荷主コード出力先の列インデックス
     * @return array 変換後のヤマトCSV用データ配列
     */
    public function transform(
        array $dataRows, 
        array $header, 
        $dbData, 
        array $yamatoIdxMap, 
        int $maxColumns, 
        $hatsuMaster, 
        int $hatsuIdx
    ): array;
}