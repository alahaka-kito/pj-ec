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

        <!-- Googleログインユーザーのみ表示する領域 -->
        @if(Auth::user() && Auth::user()->login_type === 'google')
            
            <div class="relative w-full border-b-2 border-[#E26A2C] mb-8">
                <div class="inline-block w-72 bg-[#E26A2C] text-white text-xl py-2 tracking-wider border border-green-800 text-center">
                    機能
                </div>
            </div>

            <div class="pl-2 mb-12">
                <a href="{{ route('shipping-instruction.index') }}" class="inline-block w-44 bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 rounded shadow text-center text-base">
                    出荷指示データ
                </a>
            </div>

            <div class="relative w-full border-b-2 border-[#E26A2C] mb-8">
                <div class="inline-block w-72 bg-[#E26A2C] text-white text-xl py-2 tracking-wider border border-green-800 text-center">
                    マスタメンテナンス
                </div>
            </div>

            <div class="pl-2 flex gap-4">
                <a href="{{ route('supplier-master.index') }}" class="inline-block w-44 bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 rounded shadow text-center text-base">
                    仕入先マスタ
                </a>
                
                <a href="{{ route('product-master.index') }}" class="inline-block w-44 bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 rounded shadow text-center text-base">
                    商品マスタ
                </a>
            </div>

        @endif
    </div>
</x-app-layout>