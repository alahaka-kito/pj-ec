<x-app-layout>
    <div class="p-6 text-gray-900 antialiased bg-white min-h-screen">
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
            <!-- 出荷指示データボタン -->
            <a href="{{ route('shipping-instruction.index') }}" class="inline-block bg-orange-500 hover:bg-orange-600 text-white font-bold py-4 px-8 rounded shadow text-center text-lg">
                出荷指示データ
            </a>
        </div>
    </div>
</x-app-layout>