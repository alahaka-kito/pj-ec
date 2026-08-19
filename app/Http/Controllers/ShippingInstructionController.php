<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\CsvTransformers\TikTokTransformer;

class ShippingInstructionController extends Controller
{
    // 画面表示
    public function index()
    {
        return view('shipping_instruction_data');
    }

    // CSVアップロード処理
    public function upload(Request $request)
    {
        if (!$request->hasFile('csv_file')) {
            return redirect()->back()->with('error', 'ファイルが選択されていません。');
        }

        $file = $request->file('csv_file');

        if ($file->getClientOriginalName() !== 'tiktokdata.csv') {
            return redirect()->back()->with('error', 'ファイル名が正しくありません。tiktokdata.csv をアップロードしてください。');
        }

        $file->move(storage_path('app'), 'tiktokdata.csv');

        return redirect()->back()->with('success', 'tiktokdata.csv のアップロードが完了しました。');
    }

    /**
     * メイン処理：CSV変換＆ダウンロード
     */
    public function process()
    {
        $inputFullPath = storage_path('app/tiktokdata.csv');
        $templateFullPath = storage_path('app/template/yamato_sanchoku.csv');

        if (!file_exists($inputFullPath)) {
            return redirect()->back()->with('error', 'アップロードされた tiktokdata.csv が見つかりません。先にアップロードを行ってください。');
        }
        if (!file_exists($templateFullPath)) {
            return redirect()->back()->with('error', 'テンプレートファイルが存在しません。');
        }

        // 1. 入力ファイルの読み込みと文字コード変換
        $fileContent = file_get_contents($inputFullPath);
        $encoding = mb_detect_encoding($fileContent, ['UTF-8', 'SJIS-WIN', 'SJIS', 'EUC-JP'], true);
        if ($encoding !== 'UTF-8') {
            $fileContent = mb_convert_encoding($fileContent, 'UTF-8', $encoding);
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $fileContent);
        rewind($stream);
        $sourceRows = [];
        while (($data = fgetcsv($stream)) !== false) {
            $sourceRows[] = array_map(function($value) {
                return trim(str_replace("\t", "", $value));
            }, $data);
        }
        fclose($stream);

        if (count($sourceRows) < 1) {
            return redirect()->back()->with('error', 'CSVファイルの中身が空です。');
        }

        $sourceHeader = $sourceRows[0];
        $sourceDataRows = array_slice($sourceRows, 1);

        // 2. ヤマトテンプレートの読み込みとヘッダー解析
        $templateContent = file_get_contents($templateFullPath);
        $tempEncoding = mb_detect_encoding($templateContent, ['UTF-8', 'SJIS-WIN', 'SJIS'], true);
        if ($tempEncoding !== 'UTF-8') {
            $templateContent = mb_convert_encoding($templateContent, 'UTF-8', $tempEncoding);
        }
        
        $templateStream = fopen('php://temp', 'r+');
        fwrite($templateStream, $templateContent);
        rewind($templateStream);
        $templateHeader = fgetcsv($templateStream);
        fclose($templateStream);

        if (!$templateHeader) {
            return redirect()->back()->with('error', 'テンプレートの解析に失敗しました。');
        }

        // 3. DB固定値マッピングの自動検出
        $dbMapping = [
            '真荷主コード' => ['col' => 'shin_ninushi_code', 'default_idx' => 2],
            '真荷主データ作成時刻' => ['col' => 'shin_ninushi_data_created_time', 'default_idx' => 9],
            '荷送人郵便番号' => ['col' => 'shipper_zip_code', 'default_idx' => 18],
            '荷送人電話番号' => ['col' => 'shipper_phone_number', 'default_idx' => 19],
            '荷送人住所１' => ['col' => 'shipper_address_1', 'default_idx' => 20],
            '荷送人住所２' => ['col' => 'shipper_address_2', 'default_idx' => 21],
            '荷送人名' => ['col' => 'shipper_name', 'default_idx' => 22],
            '寸法単位コード' => ['col' => 'dimension_unit_code', 'default_idx' => 27],
            '保管温度区分' => ['col' => 'storage_temperature_division', 'default_idx' => 32],
            '出荷区分' => ['col' => 'shipping_division', 'default_idx' => 34],
            'サービス区分' => ['col' => 'service_division', 'default_idx' => 44],
            'のし種別' => ['col' => 'noshi_type', 'default_idx' => 47],
            '汎用区分０１（汎用帳票種別）' => ['col' => 'general_purpose_division_01', 'default_idx' => 56],
            '汎用帳票明細数' => ['col' => 'general_form_detail_count', 'default_idx' => 61],
            '明細番号' => ['col' => 'detail_number', 'default_idx' => 90],
        ];

        $yamatoIdxMap = [];
        foreach ($dbMapping as $hName => $info) {
            $idx = false;
            foreach ($templateHeader as $k => $v) {
                if (str_contains($v, $hName)) {
                    $idx = $k;
                    break;
                }
            }
            $yamatoIdxMap[$info['col']] = ($idx !== false) ? $idx : $info['default_idx'];
        }

        // 発荷主コードの列インデックス（K列 / 10番目）を特定
        $hatsuIdx = 10;
        foreach ($templateHeader as $k => $v) {
            if (str_contains($v, '発荷主コード')) {
                $hatsuIdx = $k;
                break;
            }
        }

        $dbData = DB::table('yamato_sanchoku_shipping_instruction_data')->first();
        
        // hatsu_ninushi_data マスタを全件取得
        $hatsuMaster = DB::table('hatsu_ninushi_data')->get();

        // 4. トランスフォーマーの呼び出し
        $maxColumns = max(100, count($templateHeader));
        $transformer = new TikTokTransformer(); 
        
        $convertedRows = $transformer->transform(
            $sourceDataRows, 
            $sourceHeader, 
            $dbData, 
            $yamatoIdxMap, 
            $maxColumns, 
            $hatsuMaster, 
            $hatsuIdx
        );

        // 5. 元ファイルの削除
        if (file_exists($inputFullPath)) {
            unlink($inputFullPath);
        }

        // 6. CSVダウンロードストリームの作成
        $fileName = 'yamato_sanchoku_' . date('Ymd') . '.csv';
        
        $quotedHeader = array_map(function($v) {
            return '"' . $v . '"';
        }, $templateHeader);

        $response = new StreamedResponse(function() use ($quotedHeader, $convertedRows) {
            $stream = fopen('php://output', 'w');
            
            mb_convert_variables('SJIS-WIN', 'UTF-8', $quotedHeader);
            fwrite($stream, implode(',', $quotedHeader) . "\r\n");

            foreach ($convertedRows as $row) {
                mb_convert_variables('SJIS-WIN', 'UTF-8', $row);
                $line = implode(',', array_map(function($val) {
                    return $val ?? '';
                }, $row));
                fwrite($stream, $line . "\r\n");
            }
            
            fclose($stream);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');

        return $response;
    }
}