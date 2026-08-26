<x-app-layout>
    <div class="p-6 text-gray-900 antialiased bg-white min-h-screen text-sm">
        {{-- 最上部ログアウト・ヘッダーエリア --}}

        <div class="flex justify-between items-center mb-4">
            <div class="text-2xl font-bold text-gray-800 tracking-wider">仕入先マスタ</div>
            <a href="{{ route('home') }}" class="text-xs text-gray-500 hover:underline">ホームへ戻る</a>
        </div>

        {{-- 修正点：フラッシュメッセージ表示エリア（更新完了時に緑色のボックスで通知） --}}
        @if (session('status') || session('message') || session('success'))
            <div class="mb-4 p-4 bg-green-50 border-l-4 border-green-500 text-green-700 rounded shadow-sm font-bold flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span>{{ session('status') ?? session('message') ?? session('success') }}</span>
            </div>
        @endif

        {{-- タブナビゲーション（下線一体型デザイン） --}}
        <div class="relative border-b border-orange-500 mb-6 flex items-end">
            <div class="bg-orange-500 text-white px-8 py-2 font-bold rounded-t text-sm tracking-wide">
                仕入先詳細
            </div>
            <a href="{{ route('supplier-master.index') }}" class="text-blue-500 hover:bg-gray-50 px-8 py-2 font-medium border border-transparent border-b-0 rounded-t text-sm tracking-wide">
                仕入先一覧
            </a>
        </div>

        {{-- 詳細メインテーブル --}}
        <div class="max-w-4xl border border-gray-200 rounded-sm overflow-hidden shadow-sm bg-white mb-6">
            <table class="w-full text-left table-fixed border-collapse">
                <tbody>
                    <!-- 管理コード -->
                    <tr class="border-b border-gray-200">
                        <th class="w-1/4 bg-gray-50 p-3 font-medium text-gray-700 align-middle border-r border-gray-200">管理コード</th>
                        <td class="w-3/4 p-3 text-gray-800 align-middle">{{ $supplier->management_code }}</td>
                    </tr>
                    <!-- 発荷主コード -->
                    <tr class="border-b border-gray-200">
                        <th class="bg-gray-50 p-3 font-medium text-gray-700 align-middle border-r border-gray-200">発荷主コード</th>
                        <td class="p-3 text-gray-800 align-middle">{{ $supplier->hatsu_ninushi_code ?? '—' }}</td>
                    </tr>
                    <!-- 会社名 -->
                    <tr class="border-b border-gray-200">
                        <th class="bg-gray-50 p-3 font-medium text-gray-700 align-middle border-r border-gray-200">会社名</th>
                        <td class="p-3 text-gray-900 font-bold text-base align-middle">{{ $supplier->company_name }}</td>
                    </tr>
                    <!-- 担当者 -->
                    <tr class="border-b border-gray-200">
                        <th class="bg-gray-50 p-3 font-medium text-gray-700 align-middle border-r border-gray-200">担当者</th>
                        <td class="p-3 text-gray-800 align-middle">{{ $supplier->manager }}</td>
                    </tr>
                    <!-- 担当者連絡先 -->
                    <tr class="border-b border-gray-200">
                        <th class="bg-gray-50 p-3 font-medium text-gray-700 align-middle border-r border-gray-200">担当者連絡先</th>
                        <td class="p-3 text-gray-800 align-middle">{{ $supplier->manager_telephone_number }}</td>
                    </tr>
                    <!-- 販売先 -->
                    <tr class="border-b border-gray-200">
                        <th class="bg-gray-50 p-3 font-medium text-gray-700 align-middle border-r border-gray-200">販売先</th>
                        <td class="p-3 text-gray-800 align-middle">
                            @if(!empty($supplier->selling_places))
                                {{ implode('、', $supplier->selling_places) }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                    <!-- 住所 -->
                    <tr class="border-b border-gray-200">
                        <th class="bg-gray-50 p-3 font-medium text-gray-700 align-top border-r border-gray-200 pt-4">住所</th>
                        <td class="p-3 text-gray-800 leading-relaxed align-middle">
                            <div>〒{{ $supplier->post_code }}</div>
                            <div>{{ $supplier->main_address }}</div>
                            @if($supplier->building_name)
                                <div>{{ $supplier->building_name }}</div>
                            @endif
                        </td>
                    </tr>
                    <!-- 振込先情報 -->
                    <tr class="border-b border-gray-200">
                        <th class="bg-gray-50 p-3 font-medium text-gray-700 align-top border-r border-gray-200 pt-4">振込先情報</th>
                        <td class="p-3 text-gray-800 leading-loose align-middle">
                            <div>
                                <span class="text-gray-400 mr-1">銀行名：</span><span class="font-medium mr-4">{{ $supplier->bank_name }}</span>
                                <span class="text-gray-400 mr-1">銀行番号：</span><span class="font-medium">{{ $supplier->bank_code }}</span>
                            </div>
                            <div>
                                <span class="text-gray-400 mr-1">支店名：</span><span class="font-medium mr-4">{{ $supplier->branch_name }}</span>
                                <span class="text-gray-400 mr-1">支店番号：</span><span class="font-medium">{{ $supplier->branch_code }}</span>
                            </div>
                            <div class="border-t border-dashed border-gray-200 mt-2 pt-1 text-xs text-gray-600 leading-normal">
                                <div>口座種別：{{ $supplier->account_type == 1 ? '普通' : ($supplier->account_type == 2 ? '当座' : 'その他') }}</div>
                                <div>口座番号：{{ $supplier->account_number }}</div>
                                <div>口座名義：{{ $supplier->account_holder_name }}</div>
                            </div>
                        </td>
                    </tr>
                    <!-- 集荷場所 -->
                    <tr class="border-b border-gray-200">
                        <th class="bg-gray-50 p-3 font-medium text-gray-700 align-top border-r border-gray-200 pt-4">集荷場所</th>
                        <td class="p-3 text-gray-800 leading-relaxed align-middle">
                            @if($supplier->pickup_location_post_code || $supplier->pickup_location_main_address)
                                <div>〒{{ $supplier->pickup_location_post_code }}</div>
                                <div>{{ $supplier->pickup_location_main_address }}</div>
                                @if($supplier->pickup_location_building_name)
                                    <div>{{ $supplier->pickup_location_building_name }}</div>
                                @endif
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                    <!-- 営業日 -->
                    <tr class="border-b border-gray-200">
                        <th class="bg-gray-50 p-3 font-medium text-gray-700 align-middle border-r border-gray-200">営業日</th>
                        <td class="p-3 text-gray-800 align-middle">{{ $supplier->business_days ?? '—' }}</td>
                    </tr>
                    <!-- 支払日 -->
                    <tr class="border-b border-gray-200">
                        <th class="bg-gray-50 p-3 font-medium text-gray-700 align-middle border-r border-gray-200">支払日</th>
                        <td class="p-3 text-gray-800 align-middle">{{ $supplier->payment_date ?? '—' }}</td>
                    </tr>
                    <!-- 支払締め日 -->
                    <tr>
                        <th class="bg-gray-50 p-3 font-medium text-gray-700 align-middle border-r border-gray-200">支払締め日</th>
                        <td class="p-3 text-gray-800 align-middle">{{ $supplier->payment_closing_date ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- 下部ボタンエリア --}}
        <div class="flex gap-3">
            <a href="{{ route('supplier-master.edit', $supplier->seq) }}" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-10 rounded shadow-sm transition duration-150 tracking-wider">
                編集
            </a>
            <a href="{{ route('supplier-master.index') }}" class="bg-slate-300 hover:bg-slate-400 text-slate-700 font-bold py-2 px-10 rounded shadow-sm transition duration-150 tracking-wider">
                戻る
            </a>
        </div>
    </div>
</x-app-layout>