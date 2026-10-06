<?php

namespace App\Http\Controllers;

use App\Models\SalesMaster;
use App\Models\ProductMaster;
use App\Http\Requests\SalesMasterRequest;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Class SalesMasterController
 *
 * 販売マスタ情報（親：販売マスタ、子：バリエーション、孫：構成商品）の
 * 一覧表示、新規登録、詳細表示、編集、更新、削除を管理するコントローラー
 *
 * @package App\Http\Controllers
 */
class SalesMasterController extends Controller
{
    /**
     * 販売マスタ 一覧画面表示
     *
     * @param Request $request 検索条件（商品名等）を含むリクエストオブジェクト
     * @return View 販売マスタ一覧のビュー
     */
    public function index(Request $request)
    {
        $query = SalesMaster::withCount('variations');

        // 絞り込み検索：販売名（部分一致）
        if ($request->filled('product_name')) {
            $query->where('product_name', 'like', '%' . $request->product_name . '%');
        }

        $salesMasters = $query->orderBy('id', 'desc')->paginate(20)->withQueryString();

        return Inertia::render('SalesMaster/Index', [
            'salesMasters' => $salesMasters,
            'filters' => $request->only('product_name'),
        ]);
    }

    /**
     * 販売マスタ 新規登録画面表示
     *
     * @return View 販売新規登録のビュー
     */
    public function create()
    {
        $productMasters = ProductMaster::all(['product_management_code', 'supplier_product_name', 'stock']);
        return Inertia::render('SalesMaster/Form', ['salesMaster' => null, 'productMasters' => $productMasters]);
    }

    /**
     * 販売マスタ 新規保存処理
     *
     * @param SalesMasterRequest $request バリデーション済みのリクエストデータ
     * @return RedirectResponse 一覧画面へのリダイレクトオブジェクト
     */
    public function store(SalesMasterRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $salesMaster = SalesMaster::create([
                'product_name' => $request->product_name,
                'amazon_url'   => $request->amazon_url,
                'tiktok_url'   => $request->tiktok_url,
                'drive_path'   => $request->drive_path,
            ]);

            if ($request->has('variations') && is_array($request->variations)) {
                foreach ($request->variations as $vData) {
                    $variation = $salesMaster->variations()->create([
                        'variation_name'        => $vData['variation_name'],
                        'shipping_size'         => $vData['shipping_size'] ?? null,
                        'cool_delivery_service' => isset($vData['cool_delivery_service']) ? 1 : 0,
                        'time_delivery_service' => isset($vData['time_delivery_service']) ? 1 : 0,
                    ]);

                    if (isset($vData['products']) && is_array($vData['products'])) {
                        foreach ($vData['products'] as $pData) {
                            $variation->products()->create([
                                'product_management_code' => $pData['product_management_code'],
                                'quantity'                => $pData['quantity'],
                            ]);
                        }
                    }
                }
            }
        });

        return redirect()->route('sales-master.index')->with('success', '販売マスタを登録しました。');
    }

    /**
     * 販売マスタ 詳細画面表示
     *
     * @param int|string $id 販売マスタID
     * @return View 販売詳細のビュー
     */
    public function show($id)
    {
        $salesMaster = SalesMaster::with('variations.products.productMaster')->findOrFail($id);
        return Inertia::render('SalesMaster/Show', ['salesMaster' => $salesMaster]);
    }

    /**
     * 販売マスタ 編集画面表示
     *
     * @param int|string $id 販売マスタID
     * @return View 販売編集のビュー
     */
    public function edit($id)
    {
        $salesMaster = SalesMaster::with('variations.products')->findOrFail($id);
        $productMasters = ProductMaster::all(['product_management_code', 'supplier_product_name', 'stock']);
        return Inertia::render('SalesMaster/Form', compact('salesMaster', 'productMasters'));
    }

    /**
     * 販売マスタ 更新処理
     *
     * @param SalesMasterRequest $request バリデーション済みのリクエストデータ
     * @param int|string $id 販売マスタID
     * @return RedirectResponse 一覧画面へのリダイレクトオブジェクト
     */
    public function update(SalesMasterRequest $request, $id): RedirectResponse
    {
        $salesMaster = SalesMaster::findOrFail($id);

        DB::transaction(function () use ($request, $salesMaster) {
            $salesMaster->update([
                'product_name' => $request->product_name,
                'amazon_url'   => $request->amazon_url,
                'tiktok_url'   => $request->tiktok_url,
                'drive_path'   => $request->drive_path,
            ]);

            // 既存のバリエーション（および紐づく構成商品）を全削除後再作成
            $salesMaster->variations()->delete();

            if ($request->has('variations') && is_array($request->variations)) {
                foreach ($request->variations as $vData) {
                    $variation = $salesMaster->variations()->create([
                        'variation_name'        => $vData['variation_name'],
                        'shipping_size'         => $vData['shipping_size'] ?? null,
                        'cool_delivery_service' => isset($vData['cool_delivery_service']) ? 1 : 0,
                        'time_delivery_service' => isset($vData['time_delivery_service']) ? 1 : 0,
                    ]);

                    if (isset($vData['products']) && is_array($vData['products'])) {
                        foreach ($vData['products'] as $pData) {
                            $variation->products()->create([
                                'product_management_code' => $pData['product_management_code'],
                                'quantity'                => $pData['quantity'],
                            ]);
                        }
                    }
                }
            }
        });

        return redirect()->route('sales-master.index')->with('success', '販売マスタを更新しました。');
    }

    /**
     * 販売マスタ 削除処理
     *
     * @param int|string $id 販売マスタID
     * @return RedirectResponse 一覧画面へのリダイレクトオブジェクト
     */
    public function destroy($id): RedirectResponse
    {
        $salesMaster = SalesMaster::findOrFail($id);
        $salesMaster->delete();

        return redirect()->route('sales-master.index')->with('success', '販売マスタを削除しました。');
    }

    /**
     * 商品マスタ非同期検索 API（仕入先商品名または商品管理コードで検索）
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $keyword = $request->input('keyword');

        if (empty($keyword)) {
            return response()->json([]);
        }

        $products = ProductMaster::where('supplier_product_name', 'like', "%{$keyword}%")
            ->orWhere('product_management_code', 'like', "%{$keyword}%")
            ->select('product_management_code', 'supplier_product_name')
            ->limit(10)
            ->get();

        return response()->json($products);
    }
}
