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
                商品編集
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
        <input type="hidden" id="selected-selling-places" value="{{ json_encode(old('selling_places', $product->selling_places ?? [])) }}">

        <form method="POST" action="{{ route('product-master.update', $product->seq) }}" enctype="multipart/form-data" onsubmit="return confirm('この内容で商品情報を更新しますか？');">
            @csrf
            @method('PUT')

            <div class="max-w-4xl border border-gray-200 rounded-sm overflow-hidden shadow-sm bg-white mb-6">
                <table class="w-full text-left table-fixed border-collapse">
                    <tbody>
                        <!-- 商品画像 -->
                        <tr class="border-b border-gray-200">
                            <th class="w-1/4 bg-gray-50 p-3 font-medium text-gray-700 align-middle border-r border-gray-200">商品画像</th>
                            <td class="w-3/4 p-3 align-middle">
                                @if($product->image_path)
                                    <div class="mb-2 flex items-center gap-3">
                                        <img src="{{ $product->image_path }}" class="w-16 h-16 object-cover rounded border border-gray-200">
                                        <span class="text-xs text-gray-500">※新しい画像をアップロードすると変更されます。</span>
                                    </div>
                                @endif
                                <input type="file" name="image" accept="image/*" class="text-xs text-gray-500 file:mr-4 file:py-1.5 file:px-4 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100">
                            </td>
                        </tr>
                        <!-- 仕入先管理コード -->
                        <tr class="border-b border-gray-200">
                            <th class="bg-gray-50 p-3 font-medium text-gray-700 align-middle border-r border-gray-200">
                                仕入先管理コード <span class="text-red-500">*</span>
                            </th>
                            <td class="p-3 align-middle">
                                <select name="management_code" id="management_code" class="w-full max-w-md border border-gray-300 rounded px-2 py-1.5 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 text-xs font-mono" required>
                                    <option value="">選択してください</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->management_code }}" {{ old('management_code', $product->management_code) == $supplier->management_code ? 'selected' : '' }}>
                                            {{ $supplier->management_code }}（{{ $supplier->company_name }}）
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                        <!-- 商品管理コード -->
                        <tr class="border-b border-gray-200">
                            <th class="bg-gray-50 p-3 font-medium text-gray-700 align-middle border-r border-gray-200">
                                商品管理コード <span class="text-red-500">*</span>
                            </th>
                            <td class="p-3 align-middle">
                                <input type="text" name="product_management_code" value="{{ old('product_management_code', $product->product_management_code) }}" class="w-full max-w-md border border-gray-300 rounded px-2 py-1.5 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 text-xs font-mono" required>
                            </td>
                        </tr>
                        <!-- 仕入先商品名 -->
                        <tr class="border-b border-gray-200">
                            <th class="bg-gray-50 p-3 font-medium text-gray-700 align-middle border-r border-gray-200">
                                仕入先商品名 <span class="text-red-500">*</span>
                            </th>
                            <td class="p-3 align-middle">
                                <input type="text" name="supplier_product_name" value="{{ old('supplier_product_name', $product->supplier_product_name) }}" class="w-full border border-gray-300 rounded px-2 py-1.5 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 text-xs" required>
                            </td>
                        </tr>
                        <!-- 仕入れ値 -->
                        <tr class="border-b border-gray-200">
                            <th class="bg-gray-50 p-3 font-medium text-gray-700 align-middle border-r border-gray-200">
                                仕入れ値 <span class="text-red-500">*</span>
                            </th>
                            <td class="p-3 align-middle">
                                <div class="flex items-center gap-2">
                                    <span class="text-gray-500 font-mono text-xs">¥</span>
                                    <input type="number" name="buying_price" value="{{ old('buying_price', $product->buying_price) }}" min="0" class="w-40 border border-gray-300 rounded px-2 py-1.5 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 text-xs font-mono" required>
                                </div>
                            </td>
                        </tr>
                        <!-- 在庫数 -->
                        <tr class="border-b border-gray-200">
                            <th class="bg-gray-50 p-3 font-medium text-gray-700 align-middle border-r border-gray-200">
                                在庫数
                            </th>
                            <td class="p-3 align-middle">
                                <input type="number" name="stock" value="{{ old('stock', $product->stock ?? 0) }}" min="0" class="w-40 border border-gray-300 rounded px-2 py-1.5 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 text-xs font-mono" placeholder="0">
                            </td>
                        </tr>
                        <!-- 商品サイズ -->
                        <tr class="border-b border-gray-200">
                            <th class="bg-gray-50 p-3 font-medium text-gray-700 align-middle border-r border-gray-200">
                                商品サイズ <span class="text-red-500">*</span>
                            </th>
                            <td class="p-3 align-middle">
                                <select name="size" class="w-40 border border-gray-300 rounded px-2 py-1.5 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 text-xs font-mono" required>
                                    <option value="">選択してください</option>
                                    @foreach([60, 80, 100, 120, 140, 160, 180, 200] as $sz)
                                        <option value="{{ $sz }}" {{ old('size', $product->size) == $sz ? 'selected' : '' }}>{{ $sz }}サイズ</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                        <!-- クール宅急便 -->
                        <tr class="border-b border-gray-200">
                            <th class="bg-gray-50 p-3 font-medium text-gray-700 align-middle border-r border-gray-200">クール宅急便</th>
                            <td class="p-3 align-middle">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="cool_delivery_service" value="1" {{ old('cool_delivery_service', $product->cool_delivery_service) ? 'checked' : '' }} class="rounded border-gray-300 text-orange-500 focus:ring-orange-500">
                                    <span class="text-xs text-gray-700 font-medium">使用する</span>
                                </label>
                            </td>
                        </tr>
                        <!-- 宅急便タイムサービス -->
                        <tr class="border-b border-gray-200">
                            <th class="bg-gray-50 p-3 font-medium text-gray-700 align-middle border-r border-gray-200">宅急便タイムサービス</th>
                            <td class="p-3 align-middle">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="time_delivery_service" value="1" {{ old('time_delivery_service', $product->time_delivery_service) ? 'checked' : '' }} class="rounded border-gray-300 text-orange-500 focus:ring-orange-500">
                                    <span class="text-xs text-gray-700 font-medium">使用する</span>
                                </label>
                            </td>
                        </tr>
                        <!-- 販売先 -->
                        <tr class="border-b border-gray-200">
                            <th class="bg-gray-50 p-3 font-medium text-gray-700 align-top border-r border-gray-200 pt-4">販売先</th>
                            <td class="p-3 align-middle">
                                <div id="selling-places-container" class="flex flex-wrap gap-4">
                                    <span class="text-xs text-gray-400 italic">仕入先管理コードを選択すると販売先の選択肢が表示されます。</span>
                                </div>
                            </td>
                        </tr>
                        <!-- ドライブパス -->
                        <tr>
                            <th class="bg-gray-50 p-3 font-medium text-gray-700 align-middle border-r border-gray-200">ドライブパス</th>
                            <td class="p-3 align-middle">
                                <input type="url" name="drive_path" value="{{ old('drive_path', $product->drive_path) }}" class="w-full border border-gray-300 rounded px-2 py-1.5 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 text-xs font-mono" placeholder="https://drive.google.com/...">
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-10 rounded shadow-sm transition duration-150 tracking-wider">
                    更新
                </button>
                <a href="{{ route('product-master.show', $product->seq) }}" class="bg-slate-300 hover:bg-slate-400 text-slate-700 font-bold py-2 px-10 rounded shadow-sm text-center transition duration-150 tracking-wider">
                    キャンセル
                </a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const supplierSelect = document.getElementById('management_code');
            const container = document.getElementById('selling-places-container');
            const supplierData = JSON.parse(document.getElementById('supplier-json-data').value || '{}');
            const selectedPlaces = JSON.parse(document.getElementById('selected-selling-places').value || '[]');

            function updateSellingPlaces() {
                const code = supplierSelect.value;
                container.innerHTML = '';

                if (!code || !supplierData[code] || supplierData[code].length === 0) {
                    container.innerHTML = '<span class="text-xs text-gray-400 italic">選択した仕入先に登録されている販売先がありません。</span>';
                    return;
                }

                supplierData[code].forEach(place => {
                    const label = document.createElement('label');
                    label.className = 'inline-flex items-center gap-1.5 cursor-pointer';

                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.name = 'selling_places[]';
                    checkbox.value = place;
                    checkbox.className = 'rounded border-gray-300 text-orange-500 focus:ring-orange-500';

                    if (selectedPlaces.includes(place)) {
                        checkbox.checked = true;
                    }

                    const span = document.createElement('span');
                    span.className = 'text-xs text-gray-700';
                    span.textContent = place;

                    label.appendChild(checkbox);
                    label.appendChild(span);
                    container.appendChild(label);
                });
            }

            supplierSelect.addEventListener('change', updateSellingPlaces);
            if (supplierSelect.value) {
                updateSellingPlaces();
            }
        });
    </script>
</x-app-layout>