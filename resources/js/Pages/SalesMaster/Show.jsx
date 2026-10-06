import { Link } from '@inertiajs/react';
import { Layout } from './Shared';

const empty = <span className="text-gray-400">－</span>;

function ExternalLink({ href }) {
    return href ? <a href={href} target="_blank" rel="noreferrer" title={href} className="block truncate break-all font-mono text-xs text-blue-600 hover:underline">{href}</a> : empty;
}

export default function Show({ salesMaster, flash = {} }) {
    return <Layout active="">
        {flash.success && <div className="mb-4 rounded border-l-4 border-green-500 bg-green-50 p-4 font-bold text-green-700">{flash.success}</div>}
        <div className="mb-8 max-w-4xl overflow-hidden rounded-sm border border-gray-200 bg-white shadow-sm"><table className="w-full table-fixed border-collapse text-left text-sm"><tbody>
            <tr className="border-b"><th className="w-1/4 border-r bg-gray-50 p-4 font-medium text-gray-700">販売名</th><td className="p-4 text-base font-bold text-gray-900">{salesMaster.product_name}</td></tr>
            <tr className="border-b"><th className="border-r bg-gray-50 p-4 font-medium text-gray-700">Amazon URL</th><td className="p-4"><ExternalLink href={salesMaster.amazon_url} /></td></tr>
            <tr className="border-b"><th className="border-r bg-gray-50 p-4 font-medium text-gray-700">TikTok URL</th><td className="p-4"><ExternalLink href={salesMaster.tiktok_url} /></td></tr>
            <tr><th className="border-r bg-gray-50 p-4 font-medium text-gray-700">ドライブパス</th><td className="p-4"><ExternalLink href={salesMaster.drive_path} /></td></tr>
        </tbody></table></div>
        <h2 className="mb-4 text-lg font-bold text-gray-800">登録バリエーション</h2>
        <div className="mb-8 max-w-4xl space-y-6">{(salesMaster.variations ?? []).map(variation => <section key={variation.id} className="overflow-hidden rounded border border-orange-200 bg-white shadow-sm">
            <header className="flex items-center justify-between border-b border-orange-300 bg-orange-400 px-4 py-3"><h3 className="text-sm font-bold tracking-wide text-white">{variation.variation_name}</h3><div className="flex gap-2">{variation.shipping_size && <span className="rounded bg-white/90 px-2.5 py-0.5 text-[11px] font-bold text-orange-950">配送サイズ：{variation.shipping_size}</span>}{variation.cool_delivery_service == 1 && <span className="rounded bg-white/90 px-2.5 py-0.5 text-[11px] font-bold text-orange-950">クール宅急便</span>}{variation.time_delivery_service == 1 && <span className="rounded bg-white/90 px-2.5 py-0.5 text-[11px] font-bold text-orange-950">時間帯指定サービス</span>}</div></header>
            <div className="overflow-x-auto p-4"><table className="w-full border-collapse border border-gray-300 text-left text-xs"><thead><tr className="border-b border-gray-300 bg-gray-50 text-gray-700"><th className="border-r p-2 font-bold">対象商品</th><th className="w-24 border-r p-2 text-right font-bold">使用数</th><th className="w-24 p-2 text-right font-bold">在庫数</th></tr></thead><tbody>{(variation.products ?? []).map(item => <tr key={item.id} className="border-b border-gray-200 last:border-0 hover:bg-gray-50"><td className="border-r p-2 font-bold text-gray-800">{item.product_management_code}{item.product_master?.supplier_product_name && `（${item.product_master.supplier_product_name}）`}</td><td className="border-r p-2 text-right font-mono font-bold">{item.quantity}</td><td className="p-2 text-right font-mono font-bold">{item.product_master?.stock ?? '－'}</td></tr>)}</tbody></table></div>
        </section>)}</div>
        <div className="flex gap-3"><Link href={`/sales-master/${salesMaster.id}/edit`} className="rounded bg-orange-500 px-10 py-2 font-bold tracking-wide text-white hover:bg-orange-600">編集</Link><Link href="/sales-master" className="rounded bg-slate-300 px-10 py-2 font-bold text-slate-700">戻る</Link></div>
    </Layout>;
}
