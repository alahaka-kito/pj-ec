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
                販売編集
            </div>
            <a href="{{ route('sales-master.index') }}" class="text-blue-500 hover:bg-gray-50 px-8 py-2 font-medium border border-transparent border-b-0 rounded-t text-sm tracking-wide">
                販売一覧
            </a>
        </div>

        {{-- エラーメッセージ表示 --}}
        @if ($errors->any())
            <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded shadow-sm text-xs">
                <div class="font-bold mb-1">入力内容にエラーがあります：</div>
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- 商品マスタの検索用datalist --}}
        <datalist id="product-master-list">
            @foreach ($productMasters as $product)
                <option value="{{ $product->product_management_code }}">
                    {{ $product->supplier_product_name }} ({{ $product->product_management_code }})
                </option>
            @endforeach
        </datalist>

        {{-- 編集フォーム --}}
        <form method="POST" action="{{ route('sales-master.update', $salesMaster->id) }}" onsubmit="return confirm('販売マスタを更新してよろしいですか？');" class="max-w-5xl">
            @csrf
            @method('PUT')

            <!-- 基本情報テーブル -->
            <div class="border border-gray-200 rounded-sm overflow-hidden shadow-sm bg-white mb-8">
                <table class="w-full text-left table-fixed border-collapse">
                    <tbody>
                        <tr class="border-b border-gray-200">
                            <th class="w-1/4 bg-gray-50 p-4 font-medium text-gray-700 align-middle border-r border-gray-200">販売名 <span class="text-red-500">*</span></th>
                            <td class="w-3/4 p-4">
                                <input type="text" name="product_name" value="{{ old('product_name', $salesMaster->product_name) }}" required class="w-full border border-gray-300 rounded px-3 py-1.5 focus:border-orange-500 focus:ring-1 focus:ring-orange-500">
                            </td>
                        </tr>
                        <tr class="border-b border-gray-200">
                            <th class="bg-gray-50 p-4 font-medium text-gray-700 align-middle border-r border-gray-200">Amazon販売ページURL</th>
                            <td class="p-4">
                                <input type="url" name="amazon_url" value="{{ old('amazon_url', $salesMaster->amazon_url) }}" class="w-full border border-gray-300 rounded px-3 py-1.5 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 font-mono text-xs">
                            </td>
                        </tr>
                        <tr class="border-b border-gray-200">
                            <th class="bg-gray-50 p-4 font-medium text-gray-700 align-middle border-r border-gray-200">TikTok販売ページURL</th>
                            <td class="p-4">
                                <input type="url" name="tiktok_url" value="{{ old('tiktok_url', $salesMaster->tiktok_url) }}" class="w-full border border-gray-300 rounded px-3 py-1.5 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 font-mono text-xs">
                            </td>
                        </tr>
                        <tr>
                            <th class="bg-gray-50 p-4 font-medium text-gray-700 align-middle border-r border-gray-200">ドライブパス</th>
                            <td class="p-4">
                                <input type="text" name="drive_path" value="{{ old('drive_path', $salesMaster->drive_path) }}" class="w-full border border-gray-300 rounded px-3 py-1.5 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 font-mono text-xs">
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- バリエーション設定ブロック -->
            <div class="mb-6 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-800">バリエーション設定</h3>
                <button type="button" onclick="addVariation()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-1.5 px-4 rounded shadow-sm text-xs transition">
                    + バリエーションを追加
                </button>
            </div>

            <div id="variations-container" data-initial-variations="{{ json_encode(old('variations', $salesMaster->variations)) }}" class="space-y-6 mb-8"></div>

            <div class="flex gap-3">
                <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-10 rounded shadow-sm transition duration-150 tracking-wider">
                    更新
                </button>
                <a href="{{ route('sales-master.index') }}" class="bg-slate-300 hover:bg-slate-400 text-slate-700 font-bold py-2 px-10 rounded shadow-sm text-center transition duration-150 tracking-wider">
                    戻る
                </a>
            </div>
        </form>
    </div>

    <!-- JavaScript -->
    <script>
        let variationCount = 0;

        function addVariation(data = null) {
            const vIndex = variationCount++;
            const container = document.getElementById('variations-container');
            const card = document.createElement('div');
            card.className = 'border border-gray-300 rounded shadow-sm bg-gray-50/50 p-4 relative variation-block';
            card.id = `variation-${vIndex}`;

            const vName = data ? (data.variation_name || '') : '';
            const shippingSize = data ? (data.shipping_size || '') : '';
            const coolChecked = data && (data.cool_delivery_service == 1 || data.cool_delivery_service === true) ? 'checked' : '';
            const timeChecked = data && (data.time_delivery_service == 1 || data.time_delivery_service === true) ? 'checked' : '';

            card.innerHTML = `
                <div class="flex justify-between items-center mb-3 pb-2 border-b border-gray-200">
                    <span class="font-bold text-gray-700 text-sm">バリエーション #${vIndex + 1}</span>
                    <button type="button" onclick="document.getElementById('variation-${vIndex}').remove()" class="text-red-500 hover:text-red-700 text-xs font-bold">削除</button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">バリエーション名 <span class="text-red-500">*</span></label>
                        <input type="text" name="variations[${vIndex}][variation_name]" value="${vName}" required placeholder="例: 2本セット" class="w-full border border-gray-300 rounded px-3 py-1.5 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">配送サイズ</label>
                        <select name="variations[${vIndex}][shipping_size]" class="w-full border border-gray-300 rounded px-3 py-1.5 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 bg-white">
                            <option value="">指定なし</option>
                            <option value="60" ${shippingSize == 60 ? 'selected' : ''}>60サイズ</option>
                            <option value="80" ${shippingSize == 80 ? 'selected' : ''}>80サイズ</option>
                            <option value="100" ${shippingSize == 100 ? 'selected' : ''}>100サイズ</option>
                            <option value="120" ${shippingSize == 120 ? 'selected' : ''}>120サイズ</option>
                            <option value="140" ${shippingSize == 140 ? 'selected' : ''}>140サイズ</option>
                            <option value="160" ${shippingSize == 160 ? 'selected' : ''}>160サイズ</option>
                            <option value="180" ${shippingSize == 180 ? 'selected' : ''}>180サイズ</option>
                            <option value="200" ${shippingSize == 200 ? 'selected' : ''}>200サイズ</option>
                        </select>
                    </div>
                    <div class="flex gap-6 items-center pt-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="variations[${vIndex}][cool_delivery_service]" value="1" ${coolChecked} class="rounded border-gray-300 text-orange-500 focus:ring-orange-500">
                            <span class="text-xs font-bold text-gray-700">クール宅急便</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="variations[${vIndex}][time_delivery_service]" value="1" ${timeChecked} class="rounded border-gray-300 text-orange-500 focus:ring-orange-500">
                            <span class="text-xs font-bold text-gray-700">宅急便タイムサービス</span>
                        </label>
                    </div>
                </div>

                <!-- 対応商品ブロック -->
                <div class="bg-white p-3 rounded border border-gray-200">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-xs font-bold text-gray-600">対応商品と使用個数</span>
                        <button type="button" onclick="addProduct(${vIndex})" class="text-blue-600 hover:underline text-xs font-bold">+ 商品追加</button>
                    </div>
                    <div id="products-container-${vIndex}" class="space-y-2"></div>
                </div>
            `;

            container.appendChild(card);

            if (data && data.products && data.products.length > 0) {
                data.products.forEach(p => addProduct(vIndex, p));
            } else {
                addProduct(vIndex);
            }
        }

        function addProduct(vIndex, pData = null) {
            const pContainer = document.getElementById(`products-container-${vIndex}`);
            const pIndex = pContainer.querySelectorAll('.product-row').length;

            const code = pData ? (pData.product_management_code || '') : '';
            const quantity = pData ? (pData.quantity || pData.stock || 1) : 1;

            const row = document.createElement('div');
            row.className = 'flex items-center gap-2 product-row';
            row.innerHTML = `
                <input type="text" list="product-master-list" name="variations[${vIndex}][products][${pIndex}][product_management_code]" value="${code}" placeholder="商品名またはコードで検索..." required class="w-1/2 border border-gray-300 rounded px-2 py-1 text-xs font-mono">
                <input type="number" name="variations[${vIndex}][products][${pIndex}][quantity]" min="1" value="${quantity}" required placeholder="使用個数" class="w-1/4 border border-gray-300 rounded px-2 py-1 text-xs font-mono">
                <button type="button" onclick="this.parentElement.remove()" class="text-red-500 hover:text-red-700 text-xs font-bold px-2">×</button>
            `;
            pContainer.appendChild(row);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const rawData = document.getElementById('variations-container').dataset.initialVariations;
            let initialVariations = [];
            try { initialVariations = JSON.parse(rawData); } catch (e) {}

            if (Array.isArray(initialVariations) && initialVariations.length > 0) {
                initialVariations.forEach(v => addVariation(v));
            } else {
                addVariation();
            }
        });
    </script>
</x-app-layout>