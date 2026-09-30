import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Layout } from './Shared';

export default function Index({ suppliers, filters = {}, flash = {} }) {
    const [search, setSearch] = useState(filters.search_company ?? '');
    const rows = suppliers.data ?? [];
    return <Layout active="index">
        {flash.success && <div className="mb-6 rounded border-l-4 border-green-500 bg-green-100 p-4 font-bold text-green-700">{flash.success}</div>}
        <form onSubmit={event => { event.preventDefault(); router.get('/supplier-master', { search_company: search }, { preserveState: true }); }} className="mb-6 flex items-end gap-2 rounded border border-gray-200 bg-gray-50 p-4">
            <label className="text-sm font-medium text-gray-700">会社名検索<input value={search} onChange={event => setSearch(event.target.value)} className="mt-1 block rounded border-gray-300 py-1.5 text-sm focus:border-orange-500 focus:ring-orange-500" /></label>
            <button className="rounded bg-gray-600 px-4 py-1.5 text-sm text-white hover:bg-gray-700">検索</button>
            {search && <Link href="/supplier-master" className="mb-1.5 text-sm text-gray-500 hover:underline">クリア</Link>}
        </form>
        <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200 border text-left text-sm">
                <thead className="border-b bg-gray-100 text-gray-700"><tr>{['管理コード', '会社名', '担当者', '担当者電話番号', '操作'].map(label => <th key={label} className="px-4 py-3 font-medium">{label}</th>)}</tr></thead>
                <tbody className="divide-y divide-gray-200 bg-white">
                    {rows.length ? rows.map(supplier => <tr key={supplier.seq} className="hover:bg-gray-50">
                        <td className="whitespace-nowrap px-4 py-3 text-gray-600">{supplier.management_code}</td><td className="px-4 py-3 font-medium">{supplier.company_name}</td><td className="px-4 py-3">{supplier.manager}</td><td className="whitespace-nowrap px-4 py-3">{supplier.manager_telephone_number}</td>
                        <td className="flex gap-3 whitespace-nowrap px-4 py-3"><Link href={`/supplier-master/${supplier.seq}`} className="text-blue-600 hover:underline">詳細</Link><button onClick={() => { if (window.confirm('この仕入先を削除しますか？')) router.delete(`/supplier-master/${supplier.seq}`); }} className="text-red-500 hover:underline">削除</button></td>
                    </tr>) : <tr><td colSpan="5" className="px-4 py-8 text-center text-gray-500">登録されている仕入先はありません。</td></tr>}
                </tbody>
            </table>
        </div>
        <div className="mt-4 flex gap-2">{suppliers.links?.map((link, index) => <Link key={index} href={link.url ?? '#'} preserveScroll className={`rounded border px-3 py-1 text-sm ${link.active ? 'bg-orange-500 text-white' : 'text-gray-700'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`} dangerouslySetInnerHTML={{ __html: link.label }} />)}</div>
    </Layout>;
}
