<x-app-layout>
    <div class="p-6 text-gray-900 antialiased bg-white min-h-screen text-sm">
        {{-- ログアウト --}}

        {{-- ヘッダー --}}
        <div class="flex justify-between items-center mb-4">
            <div class="text-2xl font-bold text-gray-800 tracking-wider">商品マスタ</div>
            <a href="{{ route('home') }}" class="text-xs text-gray-500 hover:underline">ホームへ戻る</a>
        </div>

        {{-- タブナビゲーション --}}
        <div class="relative border-b border-orange-500 mb-6 flex items-end">
            <div class="bg-orange-500 text-white px-8 py-2 font-bold rounded-t text-sm tracking-wide">
                商品詳細
            </div>
            <a href="{{ route('product-master.index') }}" class="text-blue-500 hover:bg-gray-50 px-8 py-2 font-medium border border-transparent border-b-0 rounded-t text-sm tracking-wide">
                商品一覧
            </a>
        </div>

        {{-- フラッシュメッセージ --}}
        @if (session('success'))
            <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 text-green-700 rounded shadow-sm text-xs font-bold">
                {{ session('success') }}
            </div>
        @endif

        {{-- 詳細表示テーブル --}}
        <div class="max-w-4xl border border-gray-200 rounded-sm overflow-hidden shadow-sm bg-white mb-6">
            <table class="w-full text-left table-fixed border-collapse">
                <tbody>
                    <!-- 商品画像 -->
                    <tr class="border-b border-gray-200">
                        <th class="w-1/4 bg-gray-50 p-4 font-medium text-gray-700 align-top border-r border-gray-200">商品画像</th>
                        <td class="w-3/4 p-4 align-middle">
                            @if($product->image_path)
                                <img src="{{ $product->image_path }}" class="max-w-xs max-h-48 object-cover rounded border border-gray-200 shadow-sm">
                            @else
                                <span class="text-gray-400 font-mono text-xs">No Image</span>
                            @endif
                        </td>
                    </tr>
                    <!-- 商品管理コード -->
                    <tr class="border-b border-gray-200">
                        <th class="bg-gray-50 p-4 font-medium text-gray-700 align-middle border-r border-gray-200">商品管理コード</th>
                        <td class="p-4 align-middle font-mono font-medium text-gray-900 text-base">
                            {{ $product->product_management_code }}
                        </td>
                    </tr>
                    <!-- 仕入先管理コード -->
                    <tr class="border-b border-gray-200">
                        <th class="bg-gray-50 p-4 font-medium text-gray-700 align-middle border-r border-gray-200">仕入先管理コード</th>
                        <td class="p-4 align-middle text-gray-800">
                            <span class="font-mono font-bold text-gray-900 mr-2">{{ $product->management_code }}</span>
                            @if($product->supplier)
                                <span class="text-xs text-gray-500">（{{ $product->supplier->company_name }}）</span>
                            @endif
                        </td>
                    </tr>
                    <!-- 仕入先商品名 -->
                    <tr class="border-b border-gray-200">
                        <th class="bg-gray-50 p-4 font-medium text-gray-700 align-middle border-r border-gray-200">仕入先商品名</th>
                        <td class="p-4 align-middle text-gray-800 font-medium text-base">
                            {{ $product->supplier_product_name }}
                        </td>
                    </tr>
                    <!-- 仕入れ値 -->
                    <tr class="border-b border-gray-200">
                        <th class="bg-gray-50 p-4 font-medium text-gray-700 align-middle border-r border-gray-200">仕入れ値</th>
                        <td class="p-4 align-middle font-mono font-bold text-gray-900 text-base tracking-wide">
                            ¥{{ number_format($product->buying_price) }}
                        </td>
                    </tr>

                    <tr class="border-b border-gray-200">
                        <th class="bg-gray-50 p-4 font-medium text-gray-700 align-middle border-r border-gray-200">ドライブパス</th>
                        <td class="p-4 align-middle">
                            @if($product->drive_path)
                                <a href="{{ $product->drive_path }}" target="_blank" class="text-blue-600 hover:underline font-mono text-xs break-all block">
                                    {{ $product->drive_path }}
                                </a>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                    </tr>
                    <!-- 販売先 -->
                    <tr>
                        <th class="bg-gray-50 p-4 font-medium text-gray-700 align-top border-r border-gray-200 pt-5">販売先</th>
                        <td class="p-4 align-middle">
                            @if(!empty($product->selling_places) && is_array($product->selling_places))
                                <div class="flex flex-wrap gap-2">
                                    @foreach($product->selling_places as $place)
                                        <span class="bg-orange-50 text-orange-700 px-3 py-1 rounded border border-orange-200 font-medium text-xs shadow-sm">
                                            {{ $place }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-gray-400 italic text-xs">登録されている販売先はありません。</span>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex gap-3">
            <a href="{{ route('product-master.edit', $product->seq) }}" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-10 rounded shadow-sm text-center transition duration-150 tracking-wider">
                編集
            </a>
            <a href="{{ route('product-master.index') }}" class="bg-slate-300 hover:bg-slate-400 text-slate-700 font-bold py-2 px-10 rounded shadow-sm text-center transition duration-150 tracking-wider">
                戻る
            </a>
        </div>
    </div>
</x-app-layout>