import { Link } from '@inertiajs/react';
import { Layout } from './Shared';

const blank = <span className="text-gray-400">－</span>;

export default function Show({ product, flash = {} }) {
    const rows = [
        ['商品画像', product.image_path ? <img src={product.image_path} alt="商品" className="h-24 w-24 rounded border object-cover" /> : blank],
        ['仕入先', <>{product.management_code}{product.supplier?.company_name && <span className="ml-3 text-xs text-gray-500">{product.supplier.company_name}</span>}</>],
        ['商品管理コード', product.product_management_code], ['仕入先商品名', product.supplier_product_name],
        ['仕入価格', `¥${Number(product.buying_price).toLocaleString()}`], ['在庫数', Number(product.stock ?? 0).toLocaleString()],
        ['商品サイズ', product.size ? `${product.size}サイズ` : blank], ['クール宅急便', product.cool_delivery_service ? '使用する' : '使用しない'],
        ['時間帯指定サービス', product.time_delivery_service ? '使用する' : '使用しない'],
        ['販売先', product.selling_places?.length ? product.selling_places.join('、') : blank],
        ['ドライブパス', product.drive_path ? <a href={product.drive_path} target="_blank" rel="noreferrer" className="break-all text-blue-600 hover:underline">{product.drive_path}</a> : blank],
    ];
    return <Layout active="">
        {flash.success && <div className="mb-4 rounded border-l-4 border-green-500 bg-green-50 p-4 font-bold text-green-700">{flash.success}</div>}
        <div className="mb-6 max-w-4xl overflow-hidden rounded border border-gray-200 bg-white shadow-sm"><table className="w-full border-collapse text-left text-sm"><tbody>{rows.map(([label, value]) => <tr key={label} className="border-b last:border-b-0"><th className="w-1/3 border-r bg-gray-50 p-4 font-medium text-gray-700">{label}</th><td className="p-4 text-gray-800">{value || blank}</td></tr>)}</tbody></table></div>
        <div className="flex gap-3"><Link href={`/product-master/${product.seq}/edit`} className="rounded bg-orange-500 px-10 py-2 font-bold text-white hover:bg-orange-600">編集</Link><Link href="/product-master" className="rounded bg-slate-300 px-10 py-2 font-bold text-slate-700">戻る</Link></div>
    </Layout>;
}
