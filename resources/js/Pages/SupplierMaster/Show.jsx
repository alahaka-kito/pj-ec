import { Link } from '@inertiajs/react';
import { Layout } from './Shared';

const rows = [
    ['管理コード', 'management_code'], ['発荷主コード', 'hatsu_ninushi_code'], ['会社名', 'company_name'],
    ['担当者', 'manager'], ['担当者電話番号', 'manager_telephone_number'], ['販売先', 'selling_places'],
    ['住所', 'address'], ['振込先情報', 'bank'], ['集荷場所', 'pickup'], ['営業日', 'business_days'],
    ['支払日', 'payment_date'], ['支払締め日', 'payment_closing_date'],
];
const empty = <span className="text-gray-400">－</span>;

function value(supplier, key) {
    if (key === 'selling_places') return supplier.selling_places?.length ? supplier.selling_places.join('、') : empty;
    if (key === 'address') return <>{supplier.post_code && <div>〒{supplier.post_code}</div>}<div>{supplier.main_address}</div>{supplier.building_name && <div>{supplier.building_name}</div>}</>;
    if (key === 'bank') return <div className="space-y-1"><div>銀行名：{supplier.bank_name || '－'}　銀行コード：{supplier.bank_code || '－'}</div><div>支店名：{supplier.branch_name || '－'}　支店コード：{supplier.branch_code || '－'}</div><div className="border-t border-dashed pt-1 text-xs">口座種別：{supplier.account_type == 1 ? '普通' : supplier.account_type == 2 ? '当座' : '－'}<br />口座番号：{supplier.account_number || '－'}<br />口座名義：{supplier.account_holder_name || '－'}</div></div>;
    if (key === 'pickup') return supplier.pickup_location_post_code || supplier.pickup_location_main_address ? <>{supplier.pickup_location_post_code && <div>〒{supplier.pickup_location_post_code}</div>}<div>{supplier.pickup_location_main_address}</div>{supplier.pickup_location_building_name && <div>{supplier.pickup_location_building_name}</div>}</> : empty;
    return supplier[key] || empty;
}

export default function Show({ supplier, flash = {} }) {
    return <Layout active="">
        {flash.success && <div className="mb-4 rounded border-l-4 border-green-500 bg-green-50 p-4 font-bold text-green-700">{flash.success}</div>}
        <div className="mb-6 max-w-4xl overflow-hidden rounded-sm border border-gray-200 bg-white shadow-sm"><table className="w-full table-fixed border-collapse text-left text-sm"><tbody>
            {rows.map(([label, key]) => <tr key={key} className="border-b border-gray-200 last:border-b-0"><th className="w-1/4 border-r border-gray-200 bg-gray-50 p-3 align-top font-medium text-gray-700">{label}</th><td className="w-3/4 p-3 leading-relaxed text-gray-800">{value(supplier, key)}</td></tr>)}
        </tbody></table></div>
        <div className="flex gap-3"><Link href={`/supplier-master/${supplier.seq}/edit`} className="rounded bg-orange-500 px-10 py-2 font-bold text-white hover:bg-orange-600">編集</Link><Link href="/supplier-master" className="rounded bg-slate-300 px-10 py-2 font-bold text-slate-700">戻る</Link></div>
    </Layout>;
}
