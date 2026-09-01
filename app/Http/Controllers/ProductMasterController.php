<?php

namespace App\Http\Controllers;

use App\Models\ProductMaster;
use App\Models\SupplierMaster;
use App\Http\Requests\ProductMasterRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Class ProductMasterController
 *
 * 商品マスタに関するCRUD処理を制御するコントローラクラス
 *
 * @package App\Http\Controllers
 */
class ProductMasterController extends Controller
{
    /**
     * 商品一覧画面を表示（検索・ページネーション付き）
     *
     * @param \Illuminate\Http\Request $request 検索パラメータ（management_code, product_management_code, supplier_product_name）
     * @return \Illuminate\View\View
     */
    public function index(Request $request): View
    {
        // クエリビルダを初期化
        $query = ProductMaster::query();

        // 1. 仕入先管理コードで検索（部分一致）
        if ($request->filled('management_code')) {
            $query->where('management_code', 'LIKE', '%' . $request->input('management_code') . '%');
        }

        // 2. 商品管理コードで検索（部分一致）
        if ($request->filled('product_management_code')) {
            $query->where('product_management_code', 'LIKE', '%' . $request->input('product_management_code') . '%');
        }

        // 3. 仕入先商品名で検索（部分一致）
        if ($request->filled('supplier_product_name')) {
            $query->where('supplier_product_name', 'LIKE', '%' . $request->input('supplier_product_name') . '%');
        }

        // seqの降順で並び替えて20件ずつページネーション取得
        $products = $query->orderBy('seq', 'desc')->paginate(20);

        return view('product-master.index', compact('products'));
    }

    /**
     * 商品新規登録画面を表示
     *
     * @return \Illuminate\View\View
     */
    public function create(): View
    {
        // 仕入先管理コードの一覧と、JavaScript連動用の販売先データを取得
        $suppliers = SupplierMaster::all();
        $supplierSellingPlaces = $suppliers->pluck('selling_places', 'management_code');

        return view('product-master.create', compact('suppliers', 'supplierSellingPlaces'));
    }

    /**
     * 商品情報を新規登録
     *
     * @param \App\Http\Requests\ProductMasterRequest $request バリデーション済みリクエスト
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(ProductMasterRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['selling_places'] = $request->input('selling_places', []);

        // チェックボックス（未チェック時は0をセット）
        $validated['cool_delivery_service'] = $request->boolean('cool_delivery_service') ? 1 : 0;
        $validated['time_delivery_service'] = $request->boolean('time_delivery_service') ? 1 : 0;

        // 画像のアップロード処理
        if ($request->hasFile('image')) {
            // storage/app/public/product_image に保存
            $path = $request->file('image')->store('product_image', 'public');
            // DBにはブラウザからアクセス可能な /storage/product_image/... のURLパスを保存
            $validated['image_path'] = Storage::url($path);
        }

        ProductMaster::create($validated);

        return redirect()
            ->route('product-master.index')
            ->with('success', '商品情報を新規登録しました。');
    }

    /**
     * 商品詳細画面を表示
     *
     * @param int $seq 商品マスタの主キー(seq)
     * @return \Illuminate\View\View
     */
    public function show($seq): View
    {
        $product = ProductMaster::findOrFail($seq);
        return view('product-master.show', compact('product'));
    }

    /**
     * 商品編集画面を表示
     *
     * @param int $seq 商品マスタの主キー(seq)
     * @return \Illuminate\View\View
     */
    public function edit($seq): View
    {
        $product = ProductMaster::findOrFail($seq);
        $suppliers = SupplierMaster::all();
        $supplierSellingPlaces = $suppliers->pluck('selling_places', 'management_code');

        return view('product-master.edit', compact('product', 'suppliers', 'supplierSellingPlaces'));
    }

    /**
     * 商品情報を更新
     *
     * @param \App\Http\Requests\ProductMasterRequest $request バリデーション済みリクエスト
     * @param int $seq 商品マスタの主キー(seq)
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(ProductMasterRequest $request, $seq): RedirectResponse
    {
        $product = ProductMaster::findOrFail($seq);
        $validated = $request->validated();
        $validated['selling_places'] = $request->input('selling_places', []);

        // チェックボックス（未チェック時は0をセット）
        $validated['cool_delivery_service'] = $request->boolean('cool_delivery_service') ? 1 : 0;
        $validated['time_delivery_service'] = $request->boolean('time_delivery_service') ? 1 : 0;

        // 新しい画像がアップロードされた場合
        if ($request->hasFile('image')) {
            // 古い画像を削除
            if ($product->image_path) {
                $oldPath = str_replace('/storage/', '', $product->image_path);
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('image')->store('product_image', 'public');
            $validated['image_path'] = Storage::url($path);
        }

        $product->update($validated);

        return redirect()
            ->route('product-master.show', $seq)
            ->with('success', '商品情報を更新しました。');
    }

    /**
     * 商品情報を削除
     *
     * @param int $seq 商品マスタの主キー(seq)
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($seq): RedirectResponse
    {
        $product = ProductMaster::findOrFail($seq);
        $product->delete();

        return redirect()
            ->route('product-master.index')
            ->with('success', '商品情報を削除しました。');
    }
}