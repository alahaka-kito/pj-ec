<x-app-layout>
    <div class="p-6 text-gray-900 antialiased bg-white min-h-screen text-sm">
        {{-- ヘッダー --}}
        <div class="flex justify-between items-center mb-4">
            <div class="text-2xl font-bold text-gray-800 tracking-wider">販売マスタ</div>
            <a href="{{ route('home') }}" class="text-xs text-gray-500 hover:underline">ホームへ戻る</a>
        </div>

        {{-- タブナビゲーション --}}
        <div class="relative border-b border-orange-500 mb-6 flex items-end">
            <div class="bg-orange-500 text-white px-8 py-2 font-bold rounded-t text-sm tracking-wide">
                販売一覧
            </div>
            <a href="{{ route('sales-master.create') }}" class="text-blue-500 hover:bg-gray-50 px-8 py-2 font-medium border border-transparent border-b-0 rounded-t text-sm tracking-wide">
                販売新規登録
            </a>
        </div>

        {{-- フラッシュメッセージ --}}
        @if (session('success'))
            <div class="mb-4 p-3 bg-green-100 border-l-4 border-green-500 text-green-700 font-bold rounded text-xs">
                {{ session('success') }}
            </div>
        @endif

        {{-- 検索エリア --}}
        <div class="bg-gray-50 p-4 border border-gray-200 rounded mb-6">
            <form method="GET" action="{{ route('sales-master.index') }}" class="flex gap-4 items-end">
                <div class="w-1/3">
                    <label class="block text-xs font-bold text-gray-600 mb-1">販売名で検索</label>
                    <input type="text" name="product_name" value="{{ request('product_name') }}" placeholder="販売名を入力" class="w-full border border-gray-300 rounded px-3 py-1.5 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 text-sm">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="bg-gray-700 hover:bg-gray-800 text-white font-bold py-1.5 px-6 rounded shadow-sm transition">
                        検索
                    </button>
                    <a href="{{ route('sales-master.index') }}" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-1.5 px-4 rounded transition text-center flex items-center">
                        クリア
                    </a>
                </div>
            </form>
        </div>

        {{-- テーブル一覧 --}}
        <div class="border border-gray-200 rounded overflow-hidden shadow-sm mb-4">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-100 text-gray-700 border-b border-gray-200 text-xs font-bold">
                        <th class="p-3">販売名</th>
                        <th class="p-3 w-32 text-center">バリエーション数</th>
                        <th class="p-3 w-40 text-center">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-xs">
                    @forelse ($salesMasters as $master)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="p-3 font-bold text-gray-800">{{ $master->product_name }}</td>
                            <td class="p-3 text-center">{{ $master->variations_count }} 件</td>
                            <td class="p-3 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <a href="{{ route('sales-master.show', $master->id) }}" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-1 px-3 rounded shadow-sm transition text-xs inline-block">
                                        詳細
                                    </a>
                                    <form action="{{ route('sales-master.destroy', $master->id) }}" method="POST" onsubmit="return confirm('本当に削除しますか？');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-1 px-3 rounded shadow-sm transition text-xs">
                                            削除
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="p-6 text-center text-gray-500">該当する販売マスタが存在しません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $salesMasters->links() }}
    </div>
</x-app-layout>