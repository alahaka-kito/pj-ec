import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Layout, inputClass } from './Shared';

export default function Index({ shipperCodes, filters = {}, flash = {} }) {
    const [code, setCode] = useState(filters.hatsu_ninushi_code ?? '');
    const [productName, setProductName] = useState(filters.product_name ?? '');
    const rows = shipperCodes.data ?? [];

    const search = event => {
        event.preventDefault();
        router.get('/shipper-code', { hatsu_ninushi_code: code, product_name: productName }, { preserveState: true });
    };

    return <Layout active="index">
        {flash.success && <div className="mb-6 rounded border-l-4 border-green-500 bg-green-100 p-4 font-bold text-green-700">{flash.success}</div>}
        <form onSubmit={search} className="mb-6 flex flex-wrap items-end gap-3 rounded border border-gray-200 bg-gray-50 p-4">
            <label className="text-sm font-medium text-gray-700">発荷主コード<input value={code} onChange={event => setCode(event.target.value)} className={`${inputClass} w-64`} /></label>
            <label className="text-sm font-medium text-gray-700">商品名<input value={productName} onChange={event => setProductName(event.target.value)} className={`${inputClass} w-64`} /></label>
            <button className="rounded bg-gray-600 px-4 py-2 text-sm text-white hover:bg-gray-700">検索</button>
            {(code || productName) && <Link href="/shipper-code" className="mb-2 text-sm text-gray-500 hover:underline">クリア</Link>}
        </form>
        <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200 border text-left text-sm">
                <thead className="border-b bg-gray-100 text-gray-700"><tr><th className="px-4 py-3 font-medium">発荷主コード</th><th className="px-4 py-3 font-medium">商品名</th><th className="px-4 py-3 font-medium">操作</th></tr></thead>
                <tbody className="divide-y divide-gray-200 bg-white">
                    {rows.length ? rows.map(row => <tr key={row.seq} className="hover:bg-gray-50">
                        <td className="whitespace-nowrap px-4 py-3">{row.hatsu_ninushi_code}</td>
                        <td className="px-4 py-3 font-medium">{row.product_name}</td>
                        <td className="whitespace-nowrap px-4 py-3"><div className="flex gap-3"><Link href={`/shipper-code/${row.seq}/edit`} className="text-blue-600 hover:underline">修正</Link><button onClick={() => { if (window.confirm('この発荷主コードを削除しますか？')) router.delete(`/shipper-code/${row.seq}`); }} className="text-red-500 hover:underline">削除</button></div></td>
                    </tr>) : <tr><td colSpan="3" className="px-4 py-8 text-center text-gray-500">登録されている発荷主コードはありません。</td></tr>}
                </tbody>
            </table>
        </div>
        <div className="mt-4 flex gap-2">{shipperCodes.links?.map((link, index) => <Link key={index} href={link.url ?? '#'} preserveScroll className={`rounded border px-3 py-1 text-sm ${link.active ? 'bg-orange-500 text-white' : 'text-gray-700'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`} dangerouslySetInnerHTML={{ __html: link.label }} />)}</div>
    </Layout>;
}
