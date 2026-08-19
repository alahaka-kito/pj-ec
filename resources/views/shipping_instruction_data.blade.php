<x-app-layout>
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
        <!-- ヘッダーエリア -->
        <div class="flex justify-between items-center mb-10">
            <h1 class="text-2xl font-bold text-gray-800">出荷指示データ</h1>
            <a href="{{ route('home') }}" class="text-sm text-gray-600 hover:text-gray-900 underline font-medium">ホームへ戻る</a>
        </div>

        <!-- アラートメッセージ表示エリア -->
        @if (session('success'))
            <div class="mb-6 p-4 bg-green-50 border border-green-400 text-green-700 rounded-md text-sm">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-6 p-4 bg-red-50 border border-red-400 text-red-700 rounded-md text-sm">
                {{ session('error') }}
            </div>
        @endif

        <div class="border-2 border-orange-500 rounded-lg p-8 min-h-[600px] bg-white">
            
            <!-- セクション1：処理データアップロード -->
            <div class="mb-12">
                <div class="inline-block bg-orange-500 text-white px-6 py-2 text-lg font-bold rounded-t-md mb-0">
                    処理データアップロード
                </div>
                <div class="border-t border-orange-500 pt-6">
                    <form action="{{ route('shipping-instruction.upload') }}" method="POST" enctype="multipart/form-data" class="flex items-center space-x-4">
                        @csrf
                        <div class="relative">
                            <input type="file" name="csv_file" required class="border border-gray-400 rounded px-4 py-2 bg-gray-50 text-gray-700 text-sm hover:bg-gray-100 cursor-pointer">
                        </div>
                        <button type="submit" class="border border-gray-400 rounded px-6 py-2 bg-gray-50 text-gray-700 text-sm font-medium hover:bg-gray-100 shadow-sm">
                            アップロード
                        </button>
                        <span class="text-sm text-gray-600 pl-4">※ファイル名はtiktokdata.csv</span>
                    </form>
                </div>
            </div>

            <!-- セクション2：データ抽出 -->
            <div>
                <div class="inline-block bg-orange-500 text-white px-10 py-2 text-lg font-bold rounded-t-md mb-0">
                    データ抽出
                </div>
                <div class="border-t border-orange-500 pt-6">
                    <form action="{{ route('shipping-instruction.process') }}" method="POST">
                        @csrf
                        <button type="submit" class="border border-gray-400 rounded px-8 py-2 bg-gray-50 text-gray-700 text-sm font-medium hover:bg-gray-100 shadow-sm">
                            処理実行
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>