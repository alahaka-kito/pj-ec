<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    // OAuthサービス（Google等）へリダイレクト
    public function redirectToProvider()
    {
        return Socialite::driver('google')->redirect();
    }

    // 認証後のコールバック処理
    public function handleProviderCallback()
    {
        try {
            $oauthUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors(['email' => 'OAuth認証に失敗しました。']);
        }

        // 指定ドメイン（@alahaka.co.jp）のチェック
        if (!Str::endsWith($oauthUser->getEmail(), '@alahaka.co.jp')) {
            // エラー名を 'oauth_error' に指定してログイン画面に戻す
            return redirect()->route('login')->withErrors([
                'oauth_error' => 'このGoogleアカウントからはログインできません。社内ドメイン（@alahaka.co.jp）のアカウントを使用してください。'
            ]);
        }

        // メールアドレスで既存ユーザーを探す。なければ新規作成（社内ユーザー用）
        $user = User::firstOrCreate(
            ['email' => $oauthUser->getEmail()],
            [
                'name' => $oauthUser->getName() ?? $oauthUser->getNickname() ?? '社内ユーザー',
                'password' => bcrypt(Str::random(24)), // 外部用フォームでログインさせないためにランダムなパスワードを設定
                'email_verified_at' => now(), // OAuth経由なので認証済みとする
                'login_type' => 'google', // 新規作成時に 'google' を保存
            ]
        );

        // すでに 'local' で登録があったユーザーでも、Googleから入ったら 'google' に更新する
        if ($user->login_type !== 'google') {
            $user->login_type = 'google';
            $user->save();
        }

        // ログイン実行
        Auth::login($user);

        return redirect()->intended(route('home'));
    }
}