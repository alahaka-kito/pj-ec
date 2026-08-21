<x-app-layout>
    <div class="p-6 text-gray-900 antialiased bg-white min-h-screen">
        
        <!-- エラーメッセージの表示（直叩きでホームに戻された時用） -->
        @if (session('error'))
            <div class="mb-6 p-4 bg-red-100 border-l-4 border-red-500 text-red-700 font-bold rounded">
                {{ session('error') }}
            </div>
        @endif

        <!-- 上部タイトル -->
        <div class="text-2xl font-normal text-gray-800 mb-8">
            ECツール
        </div>

        <!-- オレンジの「機能」ヘッダーバー -->
        <div class="relative w-full border-b-2 border-[#E26A2C] mb-8">
            <div class="inline-block bg-[#E26A2C] text-white text-xl px-12 py-2 tracking-wider border border-green-800">
                機能
            </div>
        </div>

        <!-- ボタンエリア（左寄せ） -->
        <div class="pl-2">
            <!-- Googleログインユーザーのみ「出荷指示データボタン」を表示する -->
            @if(Auth::user() && Auth::user()->login_type === 'google')
                <!-- 出荷指示データボタン -->
                <a href="{{ route('shipping-instruction.index') }}" class="inline-block bg-orange-500 hover:bg-orange-600 text-white font-bold py-4 px-8 rounded shadow text-center text-lg">
                    出荷指示データ
                </a>
            @endif
        </div>
    </div>
</x-app-layout>