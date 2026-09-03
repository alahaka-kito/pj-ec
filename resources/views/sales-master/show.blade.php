<x-app-layout>
    <div class="p-6 text-gray-900 antialiased bg-white min-h-screen text-sm">
        <div class="flex justify-between items-center mb-4">
            <div class="text-2xl font-bold text-gray-800 tracking-wider">販売マスタ</div>
            <a href="{{ route('home') }}" class="text-xs text-gray-500 hover:underline">ホームへ戻る</a>
        </div>

        <div class="relative border-b border-orange-500 mb-6 flex items-end">
            <div class="bg-orange-500 text-white px-8 py-2 font-bold rounded-t text-sm tracking-wide">
                販売詳細
            </div>
            <a href="{{ route('sales-master.index') }}" class="text-blue-500 hover:bg-gray-50 px-8 py-2 font-medium border border-transparent border-b-0 rounded-t text-sm tracking-wide">
                販売一覧
            </a>
        </div>

        <!-- 基本情報 -->
        <div class="max-w-4xl border border-gray-200 rounded-sm overflow-hidden shadow-sm bg-white mb-8">
            <table class="w-full text-left table-fixed border-collapse">
                <tbody>
                    <tr class="border-b border-gray-200">
                        <th class="w-1/4 bg-gray-50 p-4 font-medium text-gray-700 border-r border-gray-200">販売名</th>
                        <td class="w-3/4 p-4 text-gray-900 font-bold text-base">{{ $salesMaster->product_name }}</td>
                    </tr>
                    <tr class="border-b border-gray-200">
                        <th class="bg-gray-50 p-4 font-medium text-gray-700 border-r border-gray-200">Amazon URL</th>
                        <td class="p-4">
                            @if($salesMaster->amazon_url)
                                <a href="{{ $salesMaster->amazon_url }}" target="_blank" title="{{ $salesMaster->amazon_url }}" class="text-blue-600 hover:underline font-mono text-xs block truncate">{{ $salesMaster->amazon_url }}</a>
                            @else <span class="text-gray-400">-</span> @endif
                        </td>
                    </tr>
                    <tr class="border-b border-gray-200">
                        <th class="bg-gray-50 p-4 font-medium text-gray-700 border-r border-gray-200">TikTok URL</th>
                        <td class="p-4">
                            @if($salesMaster->tiktok_url)
                                <a href="{{ $salesMaster->tiktok_url }}" target="_blank" title="{{ $salesMaster->tiktok_url }}" class="text-blue-600 hover:underline font-mono text-xs block truncate">{{ $salesMaster->tiktok_url }}</a>
                            @else <span class="text-gray-400">-</span> @endif
                        </td>
                    </tr>
                    <tr>
                        <th class="bg-gray-50 p-4 font-medium text-gray-700 border-r border-gray-200">ドライブパス</th>
                        <td class="p-4">
                            @if($salesMaster->drive_path)
                                <a href="{{ $salesMaster->drive_path }}" target="_blank" title="{{ $salesMaster->drive_path }}" class="text-blue-600 hover:underline font-mono text-xs block truncate">{{ $salesMaster->drive_path }}</a>
                            @else <span class="text-gray-400">-</span> @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- バリエーション一覧 -->
        <h3 class="text-lg font-bold text-gray-800 mb-4">登録バリエーション</h3>
        <div class="max-w-4xl space-y-6 mb-8">
            @foreach($salesMaster->variations as $variation)
                <div class="border border-orange-200 rounded shadow-sm bg-white overflow-hidden">
                    <!-- ヘッダー背景を少し薄いオレンジ（bg-orange-400）に変更 -->
                    <div class="bg-orange-400 px-4 py-3 border-b border-orange-300 flex justify-between items-center">
                        <span class="font-bold text-white text-sm tracking-wide">{{ $variation->variation_name }}</span>
                        <div class="flex gap-2">
                            @if($variation->shipping_size)
                                <span class="bg-white/90 text-orange-950 font-bold px-2.5 py-0.5 rounded text-[11px] shadow-sm">
                                    配送サイズ：{{ $variation->shipping_size }}
                                </span>
                            @endif

                            @if($variation->cool_delivery_service == 1)
                                <span class="bg-white/90 text-orange-950 font-bold px-2.5 py-0.5 rounded text-[11px] shadow-sm">クール宅急便</span>
                            @endif

                            @if($variation->time_delivery_service == 1)
                                <span class="bg-white/90 text-orange-950 font-bold px-2.5 py-0.5 rounded text-[11px] shadow-sm">タイムサービス</span>
                            @endif
                        </div>
                    </div>
                    <div class="p-4">
                        <!-- 対応商品・使用数・在庫数のテーブル -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left border border-gray-300 border-collapse">
                                <thead>
                                    <tr class="bg-gray-50 border-b border-gray-300 text-gray-700">
                                        <th class="p-2 border-r border-gray-300 font-bold">対応商品</th>
                                        <th class="p-2 w-24 border-r border-gray-300 text-right font-bold">使用数</th>
                                        <th class="p-2 w-24 text-right font-bold">在庫数</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($variation->products as $product)
                                        <tr class="border-b border-gray-200 last:border-b-0 hover:bg-gray-50/50">
                                            <td class="p-2 border-r border-gray-200 text-gray-800 font-bold">
                                                {{ $product->product_management_code }}@if(optional($product->productMaster)->supplier_product_name)({{ $product->productMaster->supplier_product_name }})@endif
                                            </td>
                                            <td class="p-2 border-r border-gray-200 text-right font-mono text-gray-800 font-bold">
                                                {{ $product->quantity }}
                                            </td>
                                            <td class="p-2 text-right font-mono text-gray-800 font-bold">
                                                {{ optional($product->productMaster)->stock ?? '-' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex gap-3">
            <a href="{{ route('sales-master.edit', $salesMaster->id) }}" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-10 rounded shadow-sm text-center transition duration-150 tracking-wider">
                編集
            </a>
            <a href="{{ route('sales-master.index') }}" class="bg-slate-300 hover:bg-slate-400 text-slate-700 font-bold py-2 px-10 rounded shadow-sm text-center transition duration-150 tracking-wider">
                戻る
            </a>
        </div>
    </div>
</x-app-layout>