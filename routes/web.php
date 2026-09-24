<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\ShippingInstructionController;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Middleware\CheckGoogleLogin;
use App\Http\Controllers\SupplierMasterController;
use App\Http\Controllers\ProductMasterController;
use App\Http\Controllers\SalesMasterController;
use App\Http\Controllers\TikTokController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/home', function () {
    return view('home');
})->middleware(['auth', 'verified'])->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// 社内用OAuth認証ルート
Route::get('/auth/redirect', [OAuthController::class, 'redirectToProvider'])->name('oauth.redirect');
Route::get('/auth/callback', [OAuthController::class, 'handleProviderCallback'])->name('oauth.callback');

// TikTok Shop OAuth用ルート（TikTokからの外部コールバックを受けるため認証外へ配置）
Route::get('/tiktok/auth', [TikTokController::class, 'redirectToAuth'])->name('tiktok.auth');
Route::get('/tiktok/callback', [TikTokController::class, 'handleCallback'])->name('tiktok.callback');

require __DIR__.'/auth.php';

// ログイン中のユーザーのみアクセス可能
Route::middleware(['auth', 'verified', CheckGoogleLogin::class])->group(function () {
    // 画面表示
    Route::get('/shipping-instruction-data', [ShippingInstructionController::class, 'index'])->name('shipping-instruction.index');
    // アップロード
    Route::post('/shipping-instruction-data/upload', [ShippingInstructionController::class, 'upload'])->name('shipping-instruction.upload');
    // 処理実行＆ダウンロード
    Route::post('/shipping-instruction-data/process', [ShippingInstructionController::class, 'process'])->name('shipping-instruction.process');

    //仕入先マスタ
    Route::resource('supplier-master', SupplierMasterController::class)->parameters([
        'supplier-master' => 'seq'
    ]);

    //商品マスタ
    Route::resource('product-master', ProductMasterController::class)->parameters([
        'product-master' => 'seq'
    ]);

    // 販売マスタ用商品検索API
    Route::get('/sales-master/search-products', [SalesMasterController::class, 'searchProducts'])->name('sales-master.search-products');
    //販売マスタ
    Route::resource('sales-master', SalesMasterController::class);

    // ★TikTok 注文データ確認用API
    Route::get('/tiktok/orders', [TikTokController::class, 'getOrders'])->name('tiktok.orders');
});