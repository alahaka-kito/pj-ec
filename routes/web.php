<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShippingInstructionController;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Middleware\CheckGoogleLogin;

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

require __DIR__.'/auth.php';

// ログイン中のユーザーのみアクセス可能
Route::middleware(['auth', 'verified', CheckGoogleLogin::class])->group(function () {
    // 画面表示
    Route::get('/shipping-instruction-data', [ShippingInstructionController::class, 'index'])->name('shipping-instruction.index');
    // アップロード
    Route::post('/shipping-instruction-data/upload', [ShippingInstructionController::class, 'upload'])->name('shipping-instruction.upload');
    // 処理実行＆ダウンロード
    Route::post('/shipping-instruction-data/process', [ShippingInstructionController::class, 'process'])->name('shipping-instruction.process');
});