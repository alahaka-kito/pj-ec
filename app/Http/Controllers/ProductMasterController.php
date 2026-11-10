<?php

namespace App\Http\Controllers;

use App\Models\ProductMaster;
use App\Models\SupplierMaster;
use App\Http\Requests\ProductMasterRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use App\Services\TikTokMasterRegistrationService;
use App\Services\AmazonMasterRegistrationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

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
    public function index(Request $request)
    {
        // クエリビルダを初期化
        $query = ProductMaster::query();

        // 1. 仕入先管理コードで検索（部分一致）
        if ($request->filled('management_code')) {
            $query->where('management_code', 'LIKE', '%' . $request->input('management_code') . '%');
        }

        // 2. 商品管理コードで検索（部分一致）
        if ($request->filled('product_management_code')) {
            $needle = $request->input('product_management_code');
            $query->where(fn ($q) => $q->where('product_management_code_tiktok', 'LIKE', "%{$needle}%")
                ->orWhere('product_management_code_amazon', 'LIKE', "%{$needle}%"));
        }

        // 3. 仕入先商品名で検索（部分一致）
        if ($request->filled('supplier_product_name')) {
            $query->where('supplier_product_name', 'LIKE', '%' . $request->input('supplier_product_name') . '%');
        }

        // seqの降順で並び替えて20件ずつページネーション取得
        $products = $query->orderBy('seq', 'desc')->paginate(20);

        return Inertia::render('ProductMaster/Index', [
            'products' => $products,
            'filters' => $request->only('management_code', 'product_management_code', 'supplier_product_name'),
        ]);
    }

    /**
     * TikTok商品を商品マスタと販売マスタへ一括登録する。
     *
     * @param Request $request TikTok商品マスタIDを含むリクエスト
     * @param TikTokMasterRegistrationService $registration 登録処理サービス
     * @return RedirectResponse 商品一覧への遷移
     */
    public function registerTikTok(Request $request, TikTokMasterRegistrationService $registration): RedirectResponse
    {
        $validated = $request->validate(['tiktok_product_id' => ['nullable', 'integer', 'exists:tiktok_product_master,id']]);
        try {
            if (!empty($validated['tiktok_product_id'])) {
                $registration->register((int) $validated['tiktok_product_id']);
                $message = 'TikTok商品を商品マスタ・販売マスタへ登録しました。';
            } else {
                $result = $registration->registerAll();
                $message = "TikTok Shop商品を取り込みました（新規 {$result['registered']}件、更新 {$result['updated']}件、スキップ {$result['skipped']}件）。";
            }
        } catch (\RuntimeException $exception) {
            return redirect()->route('product-master.index')->with('error', $exception->getMessage());
        }

        return redirect()->route('product-master.index')->with('success', $message);
    }

    /**
     * Amazon親子SKUを商品マスタと販売マスタへ一括登録する。
     *
     * @param AmazonMasterRegistrationService $registration Amazonマスタ登録サービス
     * @return RedirectResponse 商品一覧への遷移
     */
    public function registerAmazon(AmazonMasterRegistrationService $registration): RedirectResponse
    {
        $result = $registration->registerAll();
        $message = "Amazon商品を取り込みました（新規 {$result['registered']}件、更新 {$result['updated']}件、スキップ {$result['skipped']}件）。";

        return redirect()->route('product-master.index')->with('success', $message);
    }

    /**
     * TikTok商品マスタ同期コマンドを実行する。
     *
     * @return RedirectResponse 商品一覧へ実行結果を返す
     */
    public function syncTikTokProducts(): RedirectResponse
    {
        return $this->runProductSync('tiktok-sync-products', 'TikTok Shop');
    }

    /**
     * Amazon商品マスタ同期コマンドを実行する。
     *
     * @return RedirectResponse 商品一覧へ実行結果を返す
     */
    public function syncAmazonProducts(): RedirectResponse
    {
        return $this->runProductSync('amazon-sync-products', 'Amazon Shop');
    }

    /**
     * 指定された商品同期コマンドを実行し、成功または失敗を画面へ通知する。
     *
     * @param string $command 実行するArtisanコマンド名
     * @param string $shopName 画面表示用のショップ名
     * @return RedirectResponse 商品一覧への遷移
     */
    private function runProductSync(string $command, string $shopName): RedirectResponse
    {
        try {
            // 商品同期は複数回の外部API通信を行うため、PHPの既定30秒制限を解除する。
            set_time_limit(0);
            $exitCode = Artisan::call($command);
            if ($exitCode !== 0) {
                return redirect()->route('product-master.index')->with('error', "{$shopName}商品取込みに失敗しました。ログを確認してください。");
            }
        } catch (\Throwable $exception) {
            Log::error("{$shopName}商品同期コマンドの実行に失敗しました。", ['exception' => $exception->getMessage()]);

            return redirect()->route('product-master.index')->with('error', "{$shopName}商品取込みに失敗しました。ログを確認してください。");
        }

        return redirect()->route('product-master.index')->with('success', "{$shopName}商品の取込みが完了しました。");
    }

    /**
     * 商品新規登録画面を表示
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        // 仕入先管理コードの一覧と、JavaScript連動用の販売先データを取得
        $suppliers = SupplierMaster::all();
        $supplierSellingPlaces = $suppliers->pluck('selling_places', 'management_code');

        return Inertia::render('ProductMaster/Form', [
            'product' => null,
            'suppliers' => $suppliers->map(fn ($supplier) => $supplier->only('management_code', 'company_name', 'selling_places'))->values(),
        ]);
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
        $validated['product_management_code_tiktok'] = $validated['product_management_code_tiktok'] ?? '';
        $validated['product_management_code_amazon'] = $validated['product_management_code_amazon'] ?? '';
        $validated['selling_places'] = $request->input('selling_places', []);

        // 在庫数が未入力の場合は0をセット
        $validated['stock'] = $request->filled('stock') ? $request->input('stock') : 0;

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
    public function show($seq)
    {
        $product = ProductMaster::with('supplier')->findOrFail($seq);
        return Inertia::render('ProductMaster/Show', ['product' => $product]);
    }

    /**
     * 商品編集画面を表示
     *
     * @param int $seq 商品マスタの主キー(seq)
     * @return \Illuminate\View\View
     */
    public function edit($seq)
    {
        $product = ProductMaster::findOrFail($seq);
        $suppliers = SupplierMaster::all();
        $supplierSellingPlaces = $suppliers->pluck('selling_places', 'management_code');

        return Inertia::render('ProductMaster/Form', [
            'product' => $product,
            'suppliers' => $suppliers->map(fn ($supplier) => $supplier->only('management_code', 'company_name', 'selling_places'))->values(),
        ]);
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
        $validated['product_management_code_tiktok'] = $validated['product_management_code_tiktok'] ?? '';
        $validated['product_management_code_amazon'] = $validated['product_management_code_amazon'] ?? '';
        $validated['selling_places'] = $request->input('selling_places', []);

        // 在庫数が未入力の場合は0をセット
        $validated['stock'] = $request->filled('stock') ? $request->input('stock') : 0;

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
