<?php

namespace App\Http\Controllers;

use App\Models\SupplierMaster;
use App\Http\Requests\SupplierMasterRequest;
use Illuminate\Http\Request;

class SupplierMasterController extends Controller
{
    /**
     * 仕入先一覧を表示。
     */
    public function index(Request $request)
    {
        $query = SupplierMaster::query();

        // 会社名での検索処理
        if ($request->filled('search_company')) {
            $query->where('company_name', 'LIKE', '%' . $request->input('search_company') . '%');
        }

        // 1ページあたり15件でペジネーション
        $suppliers = $query->paginate(15);

        return view('supplier-master.index', compact('suppliers'));
    }

    /**
     * 新規登録画面を表示。
     */
    public function create()
    {
        return view('supplier-master.create');
    }

    /**
     * 新規登録処理を実行。
     */
    public function store(SupplierMasterRequest $request)
    {
        // バリデーション済みの値を取得
        $validated = $request->validated();

        // チェックボックスが何も選択されていない場合、空配列 [] で保存する処理を追加
        $validated['selling_places'] = $request->input('selling_places', []);

        SupplierMaster::create($validated);

        return redirect()
            ->route('supplier-master.index')
            ->with('success', '仕入先情報を新規登録しました。');
    }

    /**
     * 指定された仕入先の詳細を表示。
     */
    public function show($seq)
    {
        $supplier = SupplierMaster::findOrFail($seq);

        return view('supplier-master.show', compact('supplier'));
    }

    /**
     * 編集画面を表示。
     */
    public function edit($seq)
    {
        $supplier = SupplierMaster::findOrFail($seq);

        return view('supplier-master.edit', compact('supplier'));
    }

    /**
     * 更新処理を実行。
     */
    public function update(SupplierMasterRequest $request, $seq)
    {
        $supplier = SupplierMaster::findOrFail($seq);
        
        // バリデーション済みの値を取得
        $validated = $request->validated();

        // 編集時にチェックボックスをすべて外した場合にも対応できるよう、空配列 [] で上書きする処理を追加
        $validated['selling_places'] = $request->input('selling_places', []);

        $supplier->update($validated);

        return redirect()
            ->route('supplier-master.show', $seq)
            ->with('success', '仕入先情報を更新しました。');
    }

    /**
     * 削除処理を実行。
     */
    public function destroy($seq)
    {
        $supplier = SupplierMaster::findOrFail($seq);
        $supplier->delete();

        return redirect()
            ->route('supplier-master.index')
            ->with('success', '仕入先情報を削除しました。');
    }
}