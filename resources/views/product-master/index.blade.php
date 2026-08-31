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
                商品一覧
            </div>
            <a href="{{ route('product-master.create') }}" class="text-blue-500 hover:bg-gray-50 px-8 py-2 font-medium border border-transparent border-b-0 rounded-t text-sm tracking-wide">
                新規登録
            </a>
        </div>

        {{-- フラッシュメッセージ --}}
        @if (session('success'))
            <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 text-green-700 rounded shadow-sm text-xs font-bold">
                {{ session('success') }}
            </div>
        @endif

        {{-- 検索フォーム --}}
        <div class="mb-6 p-4 bg-gray-50 border border-gray-200 rounded shadow-sm">
            <form method="GET" action="{{ route('product-master.index') }}" class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">仕入先管理コード</label>
                    <input type="text" name="management_code" value="{{ request('management_code') }}" class="w-40 border border-gray-300 rounded px-2 py-1.5 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 text-xs font-mono" placeholder="">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">商品管理コード</label>
                    <input type="text" name="product_management_code" value="{{ request('product_management_code') }}" class="w-44 border border-gray-300 rounded px-2 py-1.5 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 text-xs font-mono" placeholder="">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">仕入先商品名</label>
                    <input type="text" name="supplier_product_name" value="{{ request('supplier_product_name') }}" class="w-64 border border-gray-300 rounded px-2 py-1.5 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 text-xs" placeholder="">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-1.5 px-6 rounded shadow-sm transition duration-150 tracking-wider text-xs">
                        検索
                    </button>
                    @if(request('management_code') || request('product_management_code') || request('supplier_product_name'))
                        <a href="{{ route('product-master.index') }}" class="bg-slate-300 hover:bg-slate-400 text-slate-700 font-bold py-1.5 px-4 rounded shadow-sm transition duration-150 text-xs text-center">
                            クリア
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- 商品一覧テーブル --}}
        <div class="border border-gray-200 rounded shadow-sm overflow-x-auto bg-white">
            <table class="w-full text-left border-collapse min-w-[1050px]">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 text-xs uppercase tracking-wider">
                        <th class="p-3 font-medium text-center w-24">画像</th>
                        <th class="p-3 font-medium">仕入先管理コード</th>
                        <th class="p-3 font-medium">商品管理コード</th>
                        <th class="p-3 font-medium">仕入先商品名</th>
                        <th class="p-3 font-medium">仕入値</th>
                        <th class="p-3 font-medium">販売先</th>
                        <th class="p-3 font-medium">ドライブパス</th>
                        <th class="p-3 font-medium text-center w-36">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-xs">
                    @forelse ($products as $product)
                        <tr class="hover:bg-gray-50 transition duration-150">
                            <!-- 画像 -->
                            <td class="p-3 align-middle text-center">
                                @if($product->image_path)
                                    <img src="{{ $product->image_path }}" class="w-12 h-12 object-cover rounded border border-gray-200 inline-block">
                                @else
                                    <span class="text-gray-400 font-mono text-[10px]">No Image</span>
                                @endif
                            </td>
                            <!-- 仕入先管理コード -->
                            <td class="p-3 align-middle font-mono font-medium text-gray-700">
                                {{ $product->management_code }}
                            </td>
                            <!-- 商品管理コード -->
                            <td class="p-3 align-middle font-mono font-medium text-gray-900">
                                {{ $product->product_management_code }}
                            </td>
                            <!-- 仕入先商品名 -->
                            <td class="p-3 align-middle text-gray-700 max-w-xs truncate" title="{{ $product->supplier_product_name }}">
                                {{ $product->supplier_product_name }}
                            </td>
                            <!-- 仕入値 -->
                            <td class="p-3 align-middle font-mono font-medium text-gray-800">
                                ¥{{ number_format($product->buying_price) }}
                            </td>
                            <!-- 販売先 -->
                            <td class="p-3 align-middle">
                                @if(!empty($product->selling_places) && is_array($product->selling_places))
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($product->selling_places as $place)
                                            <span class="bg-orange-50 text-orange-700 px-2 py-0.5 rounded border border-orange-200 font-medium text-[11px]">
                                                {{ $place }}
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <!-- ドライブパス -->
                            <td class="p-3 align-middle max-w-xs truncate">
                                @if($product->drive_path)
                                    <a href="{{ $product->drive_path }}" target="_blank" class="text-blue-500 hover:underline font-mono text-[11px] block truncate">
                                        {{ $product->drive_path }}
                                    </a>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="p-3 align-middle text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-1.5">
                                    <a href="{{ route('product-master.show', $product->seq) }}" class="inline-block bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-1 px-3 rounded border border-slate-300 transition duration-150">
                                        詳細
                                    </a>
                                    <form method="POST" action="{{ route('product-master.destroy', $product->seq) }}" onsubmit="return confirm('本当にこの商品を削除しますか？');" class="inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-3 rounded shadow-sm border border-transparent transition duration-150">
                                            削除
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-8 text-center text-gray-400 bg-gray-50">
                                該当する商品データが見つかりません。
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ページネーション --}}
        @if($products instanceof \Illuminate\Pagination\LengthAwarePaginator)
            <div class="mt-4">
                {{ $products->appends(request()->query())->links() }}
            </div>
        @endif
    </div>
</x-app-layout>