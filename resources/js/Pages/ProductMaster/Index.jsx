import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Layout } from './Shared';

const searchFields = [['management_code', '仕入先管理コード'], ['product_management_code', '商品管理コード'], ['supplier_product_name', '仕入先商品名']];

export default function Index({ products, filters = {}, flash = {} }) {
    const [search, setSearch] = useState({ management_code: filters.management_code ?? '', product_management_code: filters.product_management_code ?? '', supplier_product_name: filters.supplier_product_name ?? '' });
    const rows = products.data ?? [];
    return <Layout active="index">
        {flash.success && <div className="mb-5 rounded border-l-4 border-green-500 bg-green-50 p-4 font-bold text-green-700">{flash.success}</div>}
        <form onSubmit={event => { event.preventDefault(); router.get('/product-master', search, { preserveState: true }); }} className="mb-6 flex flex-wrap items-end gap-3 rounded border border-gray-200 bg-gray-50 p-4">
            {searchFields.map(([name, label]) => <label key={name} className="text-xs font-bold text-gray-600">{label}<input value={search[name]} onChange={event => setSearch({ ...search, [name]: event.target.value })} className="mt-1 block rounded border-gray-300 px-2 py-1.5 text-xs focus:border-orange-500 focus:ring-orange-500" /></label>)}
            <button className="rounded bg-orange-500 px-6 py-1.5 text-xs font-bold text-white">検索</button>
            {Object.values(search).some(Boolean) && <Link href="/product-master" className="rounded bg-slate-300 px-4 py-1.5 text-xs font-bold text-slate-700">クリア</Link>}
        </form>
        <div className="overflow-x-auto rounded border border-gray-200 shadow-sm"><table className="w-full min-w-[1200px] table-fixed border-collapse text-left text-xs">
            <thead className="border-b bg-gray-50 text-gray-600"><tr>{['画像','仕入先コード','商品管理コード','仕入先商品名','仕入価格','在庫数','サイズ','クール便','時間帯指定','販売先','ドライブパス','操作'].map(label => <th key={label} className="p-3 font-medium">{label}</th>)}</tr></thead>
            <tbody className="divide-y divide-gray-200">{rows.length ? rows.map(product => <tr key={product.seq} className="hover:bg-gray-50">
                <td className="p-3 text-center">{product.image_path ? <img src={product.image_path} alt="" className="inline-block h-10 w-10 rounded border object-cover" /> : <span className="text-gray-400">No Image</span>}</td>
                <td className="whitespace-nowrap p-3 font-mono">{product.management_code}</td><td className="whitespace-nowrap p-3 font-mono font-medium">{product.product_management_code}</td><td className="truncate p-3" title={product.supplier_product_name}>{product.supplier_product_name}</td>
                <td className="whitespace-nowrap p-3 font-mono">¥{Number(product.buying_price).toLocaleString()}</td><td className="p-3 text-right font-mono">{Number(product.stock ?? 0).toLocaleString()}</td><td className="p-3 text-center">{product.size ? `${product.size}サイズ` : '－'}</td>
                {[product.cool_delivery_service, product.time_delivery_service].map((enabled, i) => <td key={i} className="p-3 text-center">{enabled ? <span className="rounded bg-amber-100 px-2 py-0.5 font-bold text-amber-800">使用</span> : <span className="text-gray-400">未使用</span>}</td>)}
                <td className="p-3">{product.selling_places?.map(place => <span key={place} className="mr-1 inline-block rounded border border-orange-200 bg-orange-50 px-1.5 py-0.5 text-orange-700">{place}</span>)}</td><td className="p-3">{product.drive_path && <a href={product.drive_path} target="_blank" rel="noreferrer" className="block truncate font-mono text-blue-500 hover:underline" title={product.drive_path}>{product.drive_path}</a>}</td>
                <td className="whitespace-nowrap p-3"><Link href={`/product-master/${product.seq}`} className="rounded border bg-slate-100 px-3 py-1 font-bold text-slate-700">詳細</Link><button onClick={() => { if (window.confirm('この商品を削除しますか？')) router.delete(`/product-master/${product.seq}`); }} className="ml-2 text-red-600 hover:underline">削除</button></td>
            </tr>) : <tr><td colSpan="12" className="p-8 text-center text-gray-500">商品が登録されていません。</td></tr>}</tbody>
        </table></div>
        <div className="mt-4 flex gap-2">{products.links?.map((link, index) => <Link key={index} href={link.url ?? '#'} preserveScroll className={`rounded border px-3 py-1 text-sm ${link.active ? 'bg-orange-500 text-white' : 'text-gray-700'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`} dangerouslySetInnerHTML={{ __html: link.label }} />)}</div>
    </Layout>;
}
