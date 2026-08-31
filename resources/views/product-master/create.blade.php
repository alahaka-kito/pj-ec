<x-app-layout>
    <div class="p-6 text-gray-900 antialiased bg-white min-h-screen text-sm">

        <div class="flex justify-between items-center mb-4">
            <div class="text-2xl font-bold text-gray-800 tracking-wider">商品マスタ</div>
            <a href="{{ route('home') }}" class="text-xs text-gray-500 hover:underline">ホームへ戻る</a>
        </div>

        {{-- タブナビゲーション --}}
        <div class="relative border-b border-orange-500 mb-6 flex items-end">
            <a href="{{ route('product-master.index') }}" class="text-blue-500 hover:bg-gray-50 px-8 py-2 font-medium border border-transparent border-b-0 rounded-t text-sm tracking-wide">
                商品一覧
            </a>
            <div class="bg-orange-500 text-white px-8 py-2 font-bold rounded-t text-sm tracking-wide">
                新規登録
            </div>
        </div>

        {{-- エラーメッセージ表示 --}}
        @if ($errors->any())
            <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded shadow-sm text-xs leading-relaxed">
                <div class="font-bold mb-1">入力内容に不備があります。修正してください。</div>
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- データ連携用隠し要素 --}}
        <input type="hidden" id="supplier-json-data" value="{{ json_encode($supplierSellingPlaces) }}">
        <input type="hidden" id="old-selling-places" value="{{ json_encode(old('selling_places', [])) }}">

        <form method="POST" action="{{ route('product-master.store') }}" enctype="multipart/form-data" onsubmit="return confirm('この内容で商品を新規登録しますか？');">
            @csrf
            <div class="max-w-4xl border border-gray-200 rounded-sm overflow-hidden shadow-sm bg-white mb-6">
                <table class="w-full text-left table-fixed border-collapse">
                    <tbody>
                        <!-- 商品画像 -->
                        <tr class="border-b border-gray-200">
                            <th class="w-1/4 bg-gray-50 p-3 font-medium text-gray-700 align-middle border-r border-gray-200">商品画像</th>
                            <td class="w-3/4 p-3 align-middle">
                                <input type="file" name="image" accept="image/*" class="w-full text-xs text-gray-500 file:mr-4 file:py-1.5 file:px-4 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100">
                            </td>
                        </tr>
                        <!-- 仕入先管理コード -->
                        <tr class="border-b border-gray-200">
                            <th class="bg-gray-50 p-3 font-medium text-gray-700 align-middle border-r border-gray-200">仕入先管理コード <span class="text-red-500 text-xs">*</span></th>
                            <td class="p-3 align-middle">
                                <select id="management_code" name="management_code" class="w-full max-w-xs border border-gray-300 rounded px-3 py-1.5 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 text-sm font-mono">
                                    <option value="">選択してください</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->management_code }}" {{ old('management_code') == $supplier->management_code ? 'selected' : '' }}>
                                            {{ $supplier->management_code }} ({{ $supplier->company_name }})
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                        <!-- 商品管理コード -->
                        <tr class="border-b border-gray-200">
                            <th class="bg-gray-50 p-3 font-medium text-gray-700 align-middle border-r border-gray-200">商品管理コード <span class="text-red-500 text-xs">*</span></th>
                            <td class="p-3 align-middle">
                                <input type="text" name="product_management_code" value="{{ old('product_management_code') }}" class="w-full max-w-sm border border-gray-300 rounded px-3 py-1.5 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 text-sm font-mono" placeholder="">
                            </td>
                        </tr>
                        <!-- 仕入先商品名 -->
                        <tr class="border-b border-gray-200">
                            <th class="bg-gray-50 p-3 font-medium text-gray-700 align-middle border-r border-gray-200">仕入先商品名 <span class="text-red-500 text-xs">*</span></th>
                            <td class="p-3 align-middle">
                                <input type="text" name="supplier_product_name" value="{{ old('supplier_product_name') }}" class="w-full border border-gray-300 rounded px-3 py-1.5 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 text-sm" placeholder="">
                            </td>
                        </tr>
                        <!-- 仕入れ値 -->
                        <tr class="border-b border-gray-200">
                            <th class="bg-gray-50 p-3 font-medium text-gray-700 align-middle border-r border-gray-200">仕入れ値 <span class="text-red-500 text-xs">*</span></th>
                            <td class="p-3 align-middle">
                                <div class="flex items-center">
                                    <span class="text-gray-500 mr-2">¥</span>
                                    <input type="number" name="buying_price" value="{{ old('buying_price') }}" class="w-full max-w-xs border border-gray-300 rounded px-3 py-1.5 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 text-sm font-mono text-right" placeholder="0">
                                </div>
                            </td>
                        </tr>
                        <!-- ドライブパス -->
                        <tr class="border-b border-gray-200">
                            <th class="bg-gray-50 p-3 font-medium text-gray-700 align-middle border-r border-gray-200">ドライブパス（URL）</th>
                            <td class="p-3 align-middle">
                                <input type="url" name="drive_path" value="{{ old('drive_path') }}" class="w-full border border-gray-300 rounded px-3 py-1.5 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 text-sm font-mono" placeholder="">
                            </td>
                        </tr>
                        <!-- 販売先（仕入先に応じて動的生成＆活性化） -->
                        <tr>
                            <th class="bg-gray-50 p-3 font-medium text-gray-700 align-top border-r border-gray-200 pt-4">販売先</th>
                            <td class="p-3 align-middle">
                                <div class="flex flex-wrap gap-6" id="selling-places-container">
                                    <span class="text-gray-400 italic" id="no-supplier-notice">仕入先管理コードを選択してください。</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-10 rounded shadow-sm transition duration-150 tracking-wider">
                    登録
                </button>
                <a href="{{ route('product-master.index') }}" class="bg-slate-300 hover:bg-slate-400 text-slate-700 font-bold py-2 px-10 rounded shadow-sm transition duration-150 tracking-wider">
                    キャンセル
                </a>
            </div>
        </form>
    </div>

    {{-- 動的チェックボックス完全自動生成スクリプト --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const supplierMap = JSON.parse(document.getElementById('supplier-json-data').value || '{}');
            const oldSellingPlaces = JSON.parse(document.getElementById('old-selling-places').value || '[]');
            
            const selectEl = document.getElementById('management_code');
            const container = document.getElementById('selling-places-container');

            function updateCheckboxes() {
                const selectedCode = selectEl.value;
                const allowedPlaces = supplierMap[selectedCode] || [];

                // 一度中身をクリア
                container.innerHTML = '';

                if (!selectedCode) {
                    container.innerHTML = '<span class="text-gray-400 italic">仕入先管理コードを選択してください。</span>';
                    return;
                }

                if (allowedPlaces.length === 0) {
                    container.innerHTML = '<span class="text-red-400">※選択された仕入先に販売先が1つも登録されていません。仕入先マスタを確認してください。</span>';
                    return;
                }

                // 仕入先に登録されている販売先だけを、チェック可能な「活性状態」で自動生成
                allowedPlaces.forEach(market => {
                    const label = document.createElement('label');
                    label.className = 'inline-flex items-center cursor-pointer';

                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.name = 'selling_places[]';
                    checkbox.value = market;
                    checkbox.className = 'rounded border-gray-300 text-orange-500 focus:ring-orange-500';
                    
                    // バリデーションエラーで戻ってきた時の選択状態復元
                    if (oldSellingPlaces.includes(market)) {
                        checkbox.checked = true;
                    }

                    const span = document.createElement('span');
                    span.className = 'ml-2 text-gray-700 font-medium';
                    span.textContent = market;

                    label.appendChild(checkbox);
                    label.appendChild(span);
                    container.appendChild(label);
                });
            }

            selectEl.addEventListener('change', updateCheckboxes);
            updateCheckboxes(); // 初期実行
        });
    </script>
</x-app-layout>