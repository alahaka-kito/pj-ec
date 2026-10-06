import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Layout, inputClass } from './Shared';

export default function Index({ salesMasters, filters = {}, flash = {} }) {
    const [productName, setProductName] = useState(filters.product_name ?? '');
    const rows = salesMasters.data ?? [];
    return <Layout active="index">
        {flash.success && <div className="mb-4 rounded border-l-4 border-green-500 bg-green-100 p-3 text-xs font-bold text-green-700">{flash.success}</div>}
        <form onSubmit={event => { event.preventDefault(); router.get('/sales-master', { product_name: productName }, { preserveState: true }); }} className="mb-6 flex items-end gap-3 rounded border border-gray-200 bg-gray-50 p-4">
            <label className="w-full max-w-md text-xs font-bold text-gray-600">販売名で検索<input value={productName} onChange={event => setProductName(event.target.value)} placeholder="販売名を入力" className={`${inputClass} mt-1 text-sm font-normal`} /></label>
            <button className="rounded bg-gray-700 px-6 py-1.5 font-bold text-white hover:bg-gray-800">検索</button>
            {productName && <Link href="/sales-master" className="rounded bg-gray-200 px-4 py-1.5 font-bold text-gray-700">クリア</Link>}
        </form>
        <div className="mb-4 overflow-x-auto rounded border border-gray-200 shadow-sm"><table className="w-full border-collapse text-left">
            <thead><tr className="border-b bg-gray-100 text-xs font-bold text-gray-700"><th className="p-3">販売名</th><th className="w-32 p-3 text-center">バリエーション数</th><th className="w-40 p-3 text-center">操作</th></tr></thead>
            <tbody className="divide-y divide-gray-200 text-xs">{rows.length ? rows.map(master => <tr key={master.id} className="hover:bg-gray-50"><td className="p-3 font-bold text-gray-800">{master.product_name}</td><td className="p-3 text-center">{master.variations_count} 件</td><td className="p-3"><div className="flex items-center justify-center gap-2"><Link href={`/sales-master/${master.id}`} className="rounded bg-orange-500 px-3 py-1 font-bold text-white hover:bg-orange-600">詳細</Link><button onClick={() => { if (window.confirm('この販売マスタを削除しますか？')) router.delete(`/sales-master/${master.id}`); }} className="rounded bg-red-600 px-3 py-1 font-bold text-white hover:bg-red-700">削除</button></div></td></tr>) : <tr><td colSpan="3" className="p-6 text-center text-gray-500">該当する販売マスタがありません。</td></tr>}</tbody>
        </table></div>
        <div className="flex gap-2">{salesMasters.links?.map((link, index) => <Link key={index} href={link.url ?? '#'} preserveScroll className={`rounded border px-3 py-1 text-sm ${link.active ? 'bg-orange-500 text-white' : 'text-gray-700'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`} dangerouslySetInnerHTML={{ __html: link.label }} />)}</div>
    </Layout>;
}
