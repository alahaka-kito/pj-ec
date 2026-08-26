<x-app-layout>
    <div class="p-6 text-gray-900 antialiased bg-white min-h-screen">
        @if (session('success'))
            <div class="mb-6 p-4 bg-green-100 border-l-4 border-green-500 text-green-700 font-bold rounded">
                {{ session('success') }}
            </div>
        @endif

        {{-- ヘッダーエリア --}}
        <div class="flex justify-between items-baseline mb-4">
            <div class="text-2xl font-bold text-gray-800">仕入先マスタ</div>
            <a href="{{ route('home') }}" class="text-sm text-gray-600 hover:underline">ホームへ戻る</a>
        </div>

        {{-- タブ風ナビゲーションエリア --}}
        <div class="flex items-end border-b-2 border-orange-500 mb-6">
            <div class="bg-orange-500 text-white px-6 py-2 rounded-t font-medium border border-orange-500 border-b-0">
                仕入先一覧
            </div>
            <a href="{{ route('supplier-master.create') }}" class="text-blue-500 hover:bg-gray-50 px-6 py-2 rounded-t font-medium border border-transparent border-b-0 hover:border-gray-200">
                仕入先新規登録
            </a>
        </div>

        <!-- 検索エリア -->
        <div class="mb-6 bg-gray-50 p-4 rounded border border-gray-200">
            <form method="GET" action="{{ route('supplier-master.index') }}" class="flex items-end gap-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">会社名検索</label>
                    <input type="text" name="search_company" value="{{ request('search_company') }}" class="border-gray-300 rounded shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm py-1.5">
                </div>
                <button type="submit" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-1.5 rounded text-sm transition">検索</button>
                @if(request('search_company'))
                    <a href="{{ route('supplier-master.index') }}" class="text-sm text-gray-500 hover:underline ml-2 mb-1.5">クリア</a>
                @endif
            </form>
        </div>

        <!-- テーブル -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 border text-left text-sm">
                <thead class="bg-gray-100 text-gray-700 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 font-medium">管理コード</th>
                        <th class="px-4 py-3 font-medium">会社名</th>
                        <th class="px-4 py-3 font-medium">担当者</th>
                        <th class="px-4 py-3 font-medium">担当者連絡先</th>
                        <th class="px-4 py-3 font-medium">操作</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($suppliers as $supplier)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ $supplier->management_code }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $supplier->company_name }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $supplier->manager }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ $supplier->manager_telephone_number }}</td>
                            <td class="px-4 py-3 whitespace-nowrap flex gap-3">
                                <a href="{{ route('supplier-master.show', $supplier->seq) }}" class="text-blue-500 hover:underline">詳細</a>
                                <form action="{{ route('supplier-master.destroy', $supplier->seq) }}" method="POST" onsubmit="return confirm('本当にこの仕入先を削除しますか？');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:underline">削除</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">登録されている仕入先はありません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $suppliers->appends(request()->input())->links() }}
        </div>
    </div>
</x-app-layout>