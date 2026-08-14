<x-guest-layout>
    <div class="p-6 text-gray-900 antialiased">
        <!-- 上部タイトル -->
        <div class="text-2xl font-normal text-gray-800 mb-8">
            ECツール
        </div>

        <!-- オレンジの「ログイン認証」ヘッダーバー -->
        <div class="relative w-full border-b-2 border-[#E26A2C] mb-8">
            <div class="inline-block bg-[#E26A2C] text-white text-xl px-12 py-2 tracking-wider">
                ログイン認証
            </div>
        </div>

        <!-- ログインフォーム（枠） -->
        <!-- ★ここに「max-w-md」（横幅制限）と「shadow-lg」（影）が効く必要があります -->
        <div class="max-w-md bg-white border border-gray-100 rounded-lg shadow-lg p-8">
            
            <!-- Session Status (エラー等) -->
            <x-auth-session-status class="mb-4" :status="session('status')" />

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <!-- Email Address -->
                <div>
                    <label for="email" class="block font-medium text-sm text-gray-700 mb-1">Email</label>
                    <input id="email" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <!-- Password -->
                <div class="mt-4">
                    <label for="password" class="block font-medium text-sm text-gray-700 mb-1">Password</label>
                    <input id="password" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" type="password" name="password" required autocomplete="current-password" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <!-- Remember Me & Forgot Password -->
                <div class="flex items-center justify-between mt-4">
                    <label for="remember_me" class="inline-flex items-center">
                        <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-600" name="remember">
                        <span class="ms-2 text-sm text-gray-600">Remember me</span>
                    </label>

                    @if (Route::has('password.request'))
                        <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                            Forgot your password?
                        </a>
                    @endif
                </div>

                <!-- ログインボタン（右寄せ） -->
                <div class="flex items-center justify-end mt-6">
                    <button type="submit" class="bg-[#1C2A38] text-white px-6 py-2.5 rounded text-sm font-semibold tracking-wider hover:bg-slate-800 transition duration-150">
                        LOG IN
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-guest-layout>