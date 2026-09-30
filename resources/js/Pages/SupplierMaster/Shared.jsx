import { Link } from '@inertiajs/react';

export const inputClass = 'mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500';

export function Layout({ children, active }) {
    return <main className="min-h-screen bg-white p-6 text-gray-900 antialiased">
        <header className="mb-4 flex items-baseline justify-between">
            <h1 className="text-2xl font-bold text-gray-800">仕入先マスタ</h1>
            <a href="/home" className="text-sm text-gray-600 hover:underline">ホームへ戻る</a>
        </header>
        <nav className="mb-6 flex items-end border-b-2 border-orange-500">
            <Link href="/supplier-master" className={`rounded-t border px-6 py-2 font-medium ${active === 'index' ? 'border-orange-500 border-b-0 bg-orange-500 text-white' : 'border-transparent text-blue-500 hover:bg-gray-50'}`}>仕入先一覧</Link>
            <Link href="/supplier-master/create" className={`rounded-t border px-6 py-2 font-medium ${active === 'create' ? 'border-orange-500 border-b-0 bg-orange-500 text-white' : 'border-transparent text-blue-500 hover:bg-gray-50'}`}>仕入先新規登録</Link>
        </nav>
        {children}
    </main>;
}

export function Field({ form, name, label, required = false, placeholder = '' }) {
    return <label className="block text-sm font-medium text-gray-700">
        {label}{required && <span className="ml-1 text-xs text-red-500">※必須</span>}
        <input className={inputClass} name={name} value={form.data[name] ?? ''} placeholder={placeholder}
            onChange={event => form.setData(name, event.target.value)} />
        {form.errors[name] && <span className="mt-1 block text-xs text-red-600">{form.errors[name]}</span>}
    </label>;
}

export const fields = [
    ['management_code', '管理コード', true], ['hatsu_ninushi_code', '発荷主コード'],
    ['company_name', '会社名', true], ['manager', '担当者'], ['manager_telephone_number', '担当者電話番号'],
    ['post_code', '郵便番号', true, '123-4567'], ['main_address', '住所（都道府県・市区町村まで）', true], ['building_name', '建物名（任意）'],
    ['bank_name', '銀行名'], ['bank_code', '銀行コード'], ['branch_name', '支店名'], ['branch_code', '支店コード'],
    ['account_number', '口座番号'], ['account_holder_name', '口座名義'],
    ['pickup_location_post_code', '郵便番号'], ['pickup_location_main_address', '住所'], ['pickup_location_building_name', '建物名'],
    ['business_days', '営業日'], ['payment_date', '支払日'], ['payment_closing_date', '支払締め日'],
];
