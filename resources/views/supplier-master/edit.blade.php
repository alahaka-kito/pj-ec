<x-app-layout>
    <div class="p-6 text-gray-900 antialiased bg-white min-h-screen">
        {{-- ヘッダーエリア --}}
        <div class="flex justify-between items-baseline mb-4">
            <div class="text-2xl font-bold text-gray-800">仕入先マスタ</div>
            <a href="{{ route('home') }}" class="text-sm text-gray-600 hover:underline">ホームへ戻る</a>
        </div>

        {{-- タブ風ナビゲーションエリア --}}
        <div class="flex items-end border-b-2 border-orange-500 mb-6">
            <a href="{{ route('supplier-master.index') }}" class="text-blue-500 hover:bg-gray-50 px-6 py-2 rounded-t font-medium border border-transparent border-b-0 hover:border-gray-200">
                仕入先一覧
            </a>
            <div class="bg-orange-500 text-white px-6 py-2 rounded-t font-medium border border-orange-500 border-b-0">
                仕入先編集
            </div>
        </div>

        @if ($errors->any())
            <div class="mb-6 p-4 bg-red-100 border-l-4 border-red-500 text-red-700 rounded font-bold text-sm">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>・{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('supplier-master.update', $supplier->seq) }}" onsubmit="return confirm('この内容で変更を登録しますか？');" class="max-w-3xl bg-gray-50 p-6 border border-gray-200 rounded shadow-sm text-sm">
            @csrf
            @method('PATCH')
            
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block font-medium text-gray-700">管理コード <span class="text-red-500 text-xs">※必須</span></label>
                        <input type="text" name="management_code" value="{{ old('management_code', $supplier->management_code) }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm">
                    </div>
                    <div>
                        <label class="block font-medium text-gray-700">発荷主コード</label>
                        <input type="text" name="hatsu_ninushi_code" value="{{ old('hatsu_ninushi_code', $supplier->hatsu_ninushi_code) }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block font-medium text-gray-700">会社名 <span class="text-red-500 text-xs">※必須</span></label>
                    <input type="text" name="company_name" value="{{ old('company_name', $supplier->company_name) }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block font-medium text-gray-700">担当者</label>
                        <input type="text" name="manager" value="{{ old('manager', $supplier->manager) }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm">
                    </div>
                    <div>
                        <label class="block font-medium text-gray-700">担当者連絡先</label>
                        <input type="text" name="manager_telephone_number" value="{{ old('manager_telephone_number', $supplier->manager_telephone_number) }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block font-medium text-gray-700 mb-2">販売先</label>
                    <div class="space-x-6">
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="selling_places[]" value="Amazon" {{ in_array('Amazon', old('selling_places', $supplier->selling_places ?? [])) ? 'checked' : '' }} class="rounded border-gray-300 text-orange-500 focus:ring-orange-500">
                            <span class="ml-2 text-gray-700">Amazon</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="selling_places[]" value="Tiktok" {{ in_array('Tiktok', old('selling_places', $supplier->selling_places ?? [])) ? 'checked' : '' }} class="rounded border-gray-300 text-orange-500 focus:ring-orange-500">
                            <span class="ml-2 text-gray-700">Tiktok</span>
                        </label>
                    </div>
                </div>

                <hr class="my-4 border-gray-200">
                <h3 class="font-bold text-gray-800 text-base">住所</h3>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs text-gray-600">郵便番号 <span class="text-red-500 text-xs">※必須</span></label>
                        <input type="text" name="post_code" value="{{ old('post_code', $supplier->post_code) }}" placeholder="123-4567" class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs text-gray-600">住所（番地まで） <span class="text-red-500 text-xs">※必須</span></label>
                        <input type="text" name="main_address" value="{{ old('main_address', $supplier->main_address) }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-gray-600">建物名（部屋番号） <span class="text-gray-400 text-xs">※任意</span></label>
                    <input type="text" name="building_name" value="{{ old('building_name', $supplier->building_name) }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm">
                </div>

                <hr class="my-4 border-gray-200">
                <h3 class="font-bold text-gray-800 text-base">振込先情報</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-600">銀行名</label>
                        <input type="text" name="bank_name" value="{{ old('bank_name', $supplier->bank_name) }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">銀行番号</label>
                        <input type="text" name="bank_code" value="{{ old('bank_code', $supplier->bank_code) }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">支店名</label>
                        <input type="text" name="branch_name" value="{{ old('branch_name', $supplier->branch_name) }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">支店番号</label>
                        <input type="text" name="branch_code" value="{{ old('branch_code', $supplier->branch_code) }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-gray-600">口座種別</label>
                    <div class="mt-2 space-x-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="account_type" value="1" {{ old('account_type', $supplier->account_type) == '1' ? 'checked' : '' }} class="text-orange-500 focus:ring-orange-500">
                            <span class="ml-2">普通</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="account_type" value="2" {{ old('account_type', $supplier->account_type) == '2' ? 'checked' : '' }} class="text-orange-500 focus:ring-orange-500">
                            <span class="ml-2">当座</span>
                        </label>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-600">口座番号</label>
                        <input type="text" name="account_number" value="{{ old('account_number', $supplier->account_number) }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600">口座名義</label>
                        <input type="text" name="account_holder_name" value="{{ old('account_holder_name', $supplier->account_holder_name) }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm">
                    </div>
                </div>

                <hr class="my-4 border-gray-200">
                <h3 class="font-bold text-gray-800 text-base">集荷場所</h3>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs text-gray-600">郵便番号</label>
                        <input type="text" name="pickup_location_post_code" value="{{ old('pickup_location_post_code', $supplier->pickup_location_post_code) }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs text-gray-600">住所（番地まで）</label>
                        <input type="text" name="pickup_location_main_address" value="{{ old('pickup_location_main_address', $supplier->pickup_location_main_address) }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-gray-600">建物名（部屋番号）</label>
                    <input type="text" name="pickup_location_building_name" value="{{ old('pickup_location_building_name', $supplier->pickup_location_building_name) }}" class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm">
                </div>

                <hr class="my-4 border-gray-200">
                <h3 class="font-bold text-gray-800 text-base">営業日・支払情報</h3>
                <div>
                    <label class="block font-medium text-gray-700">営業日</label>
                    <input type="text" name="business_days" value="{{ old('business_days', $supplier->business_days) }}" placeholder="例: 毎週水曜日は定休日" class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block font-medium text-gray-700">支払日</label>
                        <input type="text" name="payment_date" value="{{ old('payment_date', $supplier->payment_date) }}" placeholder="例: 翌月25日" class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm">
                    </div>
                    <div>
                        <label class="block font-medium text-gray-700">支払締め日</label>
                        <input type="text" name="payment_closing_date" value="{{ old('payment_closing_date', $supplier->payment_closing_date) }}" placeholder="例: 毎月末日" class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm">
                    </div>
                </div>
            </div>

            <div class="mt-8 flex gap-4">
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-8 rounded shadow transition text-sm">
                    登録
                </button>
                <a href="{{ route('supplier-master.show', $supplier->seq) }}" class="bg-gray-300 hover:bg-gray-400 text-gray-700 font-bold py-2 px-6 rounded shadow transition text-sm">
                    戻る
                </a>
            </div>
        </form>
    </div>
</x-app-layout>