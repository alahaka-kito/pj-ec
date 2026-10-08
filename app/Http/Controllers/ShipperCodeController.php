<?php

namespace App\Http\Controllers;

use App\Models\ShipperCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

/**
 * 発荷主コードの一覧表示、登録、更新、削除およびCSV一括登録を扱うコントローラー。
 *
 * @package App\Http\Controllers
 */
class ShipperCodeController extends Controller
{
    /**
     * 発荷主コードを検索条件に応じて絞り込み、ページネーション付きで表示する。
     *
     * @param Request $request 発荷主コードおよび商品名の検索条件
     * @return \Inertia\Response 一覧画面と表示データ
     */
    public function index(Request $request)
    {
        $query = ShipperCode::query();

        if ($request->filled('hatsu_ninushi_code')) {
            $query->where('hatsu_ninushi_code', 'like', '%' . $request->input('hatsu_ninushi_code') . '%');
        }

        if ($request->filled('product_name')) {
            $query->where('product_name', 'like', '%' . $request->input('product_name') . '%');
        }

        return Inertia::render('ShipperCode/Index', [
            'shipperCodes' => $query->orderBy('seq', 'desc')->paginate(15)->withQueryString(),
            'filters' => $request->only('hatsu_ninushi_code', 'product_name'),
        ]);
    }

    /**
     * 発荷主コードの新規登録画面を表示する。
     *
     * @return \Inertia\Response 新規登録画面
     */
    public function create()
    {
        return Inertia::render('ShipperCode/Form', ['shipperCode' => null]);
    }

    /**
     * 入力値を検証し、新しい発荷主コードを登録する。
     *
     * @param Request $request 登録する発荷主コードと商品名
     * @return \Illuminate\Http\RedirectResponse 一覧画面へのリダイレクト
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'hatsu_ninushi_code' => ['required', 'string', 'max:50'],
            'product_name' => ['required', 'string', 'max:255'],
        ], [
            'required' => ':attributeは必須項目です。',
            'string' => ':attributeは文字列で入力してください。',
            'max' => ':attributeは:max文字以内で入力してください。',
        ], [
            'hatsu_ninushi_code' => '発荷主コード',
            'product_name' => '商品名',
        ]);

        ShipperCode::create($validated);

        return redirect()->route('shipper-code.index')->with('success', '発荷主コードを登録しました。');
    }

    /**
     * shippercode.csv のA列・B列を検証し、全行をトランザクション内で一括登録する。
     *
     * @param Request $request アップロードされたCSVファイル
     * @return \Illuminate\Http\RedirectResponse 登録結果または入力エラーを伴うリダイレクト
     */
    public function import(Request $request)
    {
        $validated = $request->validate([
            'csv_file' => ['required', 'file', 'max:10240'],
        ], [
            'required' => ':attributeを選択してください。',
            'file' => ':attributeを正しく選択してください。',
            'max' => ':attributeは10MB以内にしてください。',
        ], [
            'csv_file' => 'CSVファイル',
        ]);

        $file = $validated['csv_file'];
        if ($file->getClientOriginalName() !== 'shippercode.csv') {
            return back()->withErrors(['csv_file' => 'ファイル名を shippercode.csv にしてください。']);
        }

        $contents = file_get_contents($file->getRealPath());
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        }
        if (!mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'SJIS-win');
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);

        $records = [];
        $errors = [];
        $line = 0;
        while (($columns = fgetcsv($stream, 0, ',', '"', '\\')) !== false) {
            $line++;
            $columns = array_map(static fn ($value) => trim((string) $value), $columns);

            if (count(array_filter($columns, static fn ($value) => $value !== '')) === 0) {
                continue;
            }

            if ($line === 1 && in_array($columns, [
                ['発荷主コード', '商品名'],
                ['hatsu_ninushi_code', 'product_name'],
            ], true)) {
                continue;
            }

            if (count($columns) !== 2) {
                $errors[] = "{$line}行目: A列に発荷主コード、B列に商品名を指定してください。";
                continue;
            }

            $validator = Validator::make([
                'hatsu_ninushi_code' => $columns[0],
                'product_name' => $columns[1],
            ], [
                'hatsu_ninushi_code' => ['required', 'string', 'max:50'],
                'product_name' => ['required', 'string', 'max:255'],
            ], [
                'required' => ':attributeは必須項目です。',
                'string' => ':attributeは文字列で入力してください。',
                'max' => ':attributeは:max文字以内で入力してください。',
            ], [
                'hatsu_ninushi_code' => '発荷主コード',
                'product_name' => '商品名',
            ]);

            if ($validator->fails()) {
                $errors[] = "{$line}行目: 発荷主コードと商品名を入力し、文字数制限内にしてください。";
                continue;
            }

            $records[] = $validator->validated();
        }
        fclose($stream);

        if ($errors) {
            return back()->withErrors(['csv_file' => implode("\n", array_slice($errors, 0, 20))]);
        }
        if (!$records) {
            return back()->withErrors(['csv_file' => 'CSVに登録するデータがありません。']);
        }

        DB::transaction(function () use ($records) {
            foreach (array_chunk($records, 500) as $chunk) {
                $timestamp = now();
                $rows = array_map(static fn ($record) => $record + [
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ], $chunk);
                DB::table('hatsu_ninushi_data')->insert($rows);
            }
        });

        return redirect()->route('shipper-code.index')->with('success', count($records) . '件の発荷主コードをCSVから登録しました。');
    }

    /**
     * 指定された発荷主コードの修正画面を表示する。
     *
     * @param int|string $seq 発荷主コードデータの主キー
     * @return \Inertia\Response 修正画面と対象データ
     */
    public function edit($seq)
    {
        return Inertia::render('ShipperCode/Form', [
            'shipperCode' => ShipperCode::findOrFail($seq),
        ]);
    }

    /**
     * 入力値を検証し、指定された発荷主コードを更新する。
     *
     * @param Request $request 更新する発荷主コードと商品名
     * @param int|string $seq 更新対象データの主キー
     * @return \Illuminate\Http\RedirectResponse 一覧画面へのリダイレクト
     */
    public function update(Request $request, $seq)
    {
        $validated = $request->validate([
            'hatsu_ninushi_code' => ['required', 'string', 'max:50'],
            'product_name' => ['required', 'string', 'max:255'],
        ], [
            'required' => ':attributeは必須項目です。',
            'string' => ':attributeは文字列で入力してください。',
            'max' => ':attributeは:max文字以内で入力してください。',
        ], [
            'hatsu_ninushi_code' => '発荷主コード',
            'product_name' => '商品名',
        ]);

        ShipperCode::findOrFail($seq)->update($validated);

        return redirect()->route('shipper-code.index')->with('success', '発荷主コードを更新しました。');
    }

    /**
     * 指定された発荷主コードを削除する。
     *
     * @param int|string $seq 削除対象データの主キー
     * @return \Illuminate\Http\RedirectResponse 一覧画面へのリダイレクト
     */
    public function destroy($seq)
    {
        ShipperCode::findOrFail($seq)->delete();

        return redirect()->route('shipper-code.index')->with('success', '発荷主コードを削除しました。');
    }
}
