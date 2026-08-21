<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>ECツール - ログイン</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts (Viteを使う場合) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- CSS (Tailwindを使わない場合、以下に直書き) -->
    <style>
        body {
            font-family: 'Figtree', sans-serif;
            background-color: #f3f4f6; /* 背景色 */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        /* 外枠のオレンジ線 */
        .page-container {
            border: 2px solid #ed8936; /* orange-500 */
            background-color: #ffffff;
            padding: 2.5rem; /* p-10 */
            width: 100%;
            max-width: 1200px; /* 大体の幅 */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); /* shadow-md */
        }

        /* ページタイトル */
        .page-title {
            font-size: 1.875rem; /* text-3xl */
            margin-bottom: 2.5rem; /* mb-10 */
        }

        /* セクション見出し (オレンジ背景 + アンダーライン) */
        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem; /* mb-6 */
            border-bottom: 2px solid #ed8936; /* 下線 */
        }

        .section-title {
            background-color: #ed8936; /* orange-500 */
            color: #ffffff;
            padding: 0.25rem 0.75rem; /* py-1 px-3 */
            font-size: 1.125rem; /* text-lg */
            margin-right: 0.75rem; /* タイトルと線の間隔 */
        }

        /* ログインフォームエリア (左寄せ) */
        .form-area {
            display: flex;
            justify-content: flex-start; /* 左寄せ */
            margin-bottom: 2.5rem; /* mb-10 */
        }

        /* フォーム自体の白いカード */
        .form-card {
            background-color: #ffffff;
            padding: 1.5rem; /* p-6 */
            border-radius: 0.375rem; /* rounded */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); /* shadow-md */
            width: 100%;
            max-width: 400px; /* フォームの幅 */
        }

        /* 入力フィールド */
        .input-group {
            margin-bottom: 1rem; /* mb-4 */
        }

        .input-label {
            display: block;
            font-size: 0.875rem; /* text-sm */
            color: #4b5563; /* gray-600 */
            margin-bottom: 0.25rem; /* mb-1 */
        }

        .input-field {
            width: 100%;
            padding: 0.5rem 0.75rem; /* p-2 */
            border: 1px solid #d1d5db; /* gray-300 */
            border-radius: 0.25rem; /* rounded-sm */
        }

        /* Remember me */
        .remember-me {
            display: flex;
            align-items: center;
            margin-bottom: 1rem; /* mb-4 */
            font-size: 0.875rem; /* text-sm */
            color: #4b5563; /* gray-600 */
        }

        .remember-me input {
            margin-right: 0.5rem; /* space-x-2 */
        }

        /* フォーム下部アクション */
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .forgot-password {
            font-size: 0.875rem; /* text-sm */
            color: #6b7280; /* gray-500 */
            text-decoration: underline;
        }

        .login-button {
            background-color: #111827; /* gray-900 */
            color: #ffffff;
            padding: 0.5rem 1rem; /* px-4 py-2 */
            border: none;
            border-radius: 0.25rem; /* rounded-sm */
            font-weight: 600;
            cursor: pointer;
        }

        /* 社内ログイン (Google) エリア */
        .internal-login-area {
            text-align: left;
        }

        /* Googleログインボタン (添付画像に似せた色) */
        .google-button {
            display: inline-flex;
            align-items: center;
            background-color: #2563eb; /* blue-600 */
            color: #ffffff;
            padding: 0.75rem 1.5rem; /* py-3 px-6 */
            border: none;
            border-radius: 0.375rem; /* rounded */
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
        }

    </style>
</head>
<body>
    <div class="page-container">
        <h1 class="page-title">ECツール</h1>

        <!-- ログイン認証セクション -->
        <div class="section-header">
            <span class="section-title">ログイン認証</span>
        </div>

        <div class="form-area">
            <div class="form-card">
                <!-- ログインフォーム -->
                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <!-- Email Address -->
                    <div class="input-group">
                        <label for="email" class="input-label">Email</label>
                        <input id="email" class="input-field" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                        @if ($errors->has('email'))
                            <p style="color: #dc2626; font-size: 0.75rem; margin-top: 0.25rem;">{{ $errors->first('email') }}</p>
                        @endif
                    </div>

                    <!-- Password -->
                    <div class="input-group">
                        <label for="password" class="input-label">Password</label>
                        <input id="password" class="input-field" type="password" name="password" required autocomplete="current-password" />
                        @if ($errors->has('password'))
                            <p style="color: #dc2626; font-size: 0.75rem; margin-top: 0.25rem;">{{ $errors->first('password') }}</p>
                        @endif
                    </div>

                    <!-- Remember Me -->
                    <div class="remember-me">
                        <input id="remember_me" type="checkbox" name="remember">
                        <label for="remember_me">Remember me</label>
                    </div>

                    <div class="form-actions">
                        @if (Route::has('password.request'))
                            <a class="forgot-password" href="{{ route('password.request') }}">
                                {{ __('Forgot your password?') }}
                            </a>
                        @endif

                        <button type="submit" class="login-button">
                            LOG IN
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 社内ログイン認証セクション -->
        <div class="section-header">
            <span class="section-title">社内ログイン認証</span>
        </div>

        <div class="internal-login-area">
            <!-- Googleログイン専用の赤文字エラーメッセージ -->
            @if ($errors->has('oauth_error'))
                <p style="color: #dc2626; font-weight: bold; margin-bottom: 1rem; font-size: 0.875rem;">
                    {{ $errors->first('oauth_error') }}
                </p>
            @endif

            <!-- Googleログインボタン -->
            <a href="{{ route('oauth.redirect') }}" class="google-button">
                Googleアカウントでログイン (@alahaka.co.jp)
            </a>
        </div>
    </div>
</body>
</html>