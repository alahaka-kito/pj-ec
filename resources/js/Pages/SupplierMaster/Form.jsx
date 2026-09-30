import { Link, useForm } from '@inertiajs/react';
import { Field, fields, inputClass, Layout } from './Shared';

const sections = [
    ['基本情報', ['management_code', 'hatsu_ninushi_code', 'company_name', 'manager', 'manager_telephone_number']],
    ['住所', ['post_code', 'main_address', 'building_name']],
    ['振込先情報', ['bank_name', 'bank_code', 'branch_name', 'branch_code', 'account_number', 'account_holder_name']],
    ['集荷場所', ['pickup_location_post_code', 'pickup_location_main_address', 'pickup_location_building_name']],
    ['営業日・支払情報', ['business_days', 'payment_date', 'payment_closing_date']],
];

export default function Form({ supplier }) {
    const editing = Boolean(supplier);
    const initial = Object.fromEntries(fields.map(([name]) => [name, supplier?.[name] ?? '']));
    initial.selling_places = supplier?.selling_places ?? [];
    initial.account_type = String(supplier?.account_type ?? '1');
    const form = useForm(initial);
    const submit = event => {
        event.preventDefault();
        editing ? form.put(`/supplier-master/${supplier.seq}`) : form.post('/supplier-master');
    };
    return <Layout active={editing ? '' : 'create'}>
        <form onSubmit={submit} className="max-w-3xl rounded border border-gray-200 bg-gray-50 p-6 text-sm shadow-sm">
            {Object.keys(form.errors).length > 0 && <div className="mb-5 rounded border-l-4 border-red-500 bg-red-100 p-4 text-red-700"><p className="font-bold">入力内容を確認してください。</p><ul className="mt-2 list-inside list-disc">{Object.values(form.errors).map((error, index) => <li key={index}>{error}</li>)}</ul></div>}
            {sections.map(([title, names]) => <section key={title} className="mb-6">
                <h2 className="mb-3 border-b border-gray-200 pb-2 text-base font-bold text-gray-800">{title}</h2>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">{names.map(name => { const field = fields.find(item => item[0] === name); return <Field key={name} form={form} name={name} label={field[1]} required={field[2]} placeholder={field[3] ?? ''} />; })}</div>
                {title === '基本情報' && <div className="mt-4"><div className="mb-2 font-medium text-gray-700">販売先</div><div className="flex gap-6">{['Amazon', 'Tiktok'].map(place => <label key={place} className="inline-flex items-center gap-2"><input type="checkbox" checked={form.data.selling_places.includes(place)} onChange={event => form.setData('selling_places', event.target.checked ? [...form.data.selling_places, place] : form.data.selling_places.filter(value => value !== place))} className="rounded border-gray-300 text-orange-500 focus:ring-orange-500" />{place}</label>)}</div>{form.errors.selling_places && <p className="mt-1 text-xs text-red-600">{form.errors.selling_places}</p>}</div>}
                {title === '振込先情報' && <div className="mt-4"><div className="mb-2 font-medium text-gray-700">口座種別</div><div className="flex gap-6">{[['1', '普通'], ['2', '当座']].map(([value, label]) => <label key={value} className="inline-flex items-center gap-2"><input type="radio" name="account_type" value={value} checked={form.data.account_type === value} onChange={event => form.setData('account_type', event.target.value)} className="text-orange-500 focus:ring-orange-500" />{label}</label>)}</div></div>}
            </section>)}
            <div className="flex gap-4"><button disabled={form.processing} onClick={event => { if (!window.confirm(editing ? 'この内容で更新しますか？' : 'この内容で登録しますか？')) event.preventDefault(); }} className="rounded bg-emerald-600 px-8 py-2 font-bold text-white hover:bg-emerald-700 disabled:opacity-50">{form.processing ? '送信中…' : editing ? '更新' : '登録'}</button><Link href={editing ? `/supplier-master/${supplier.seq}` : '/supplier-master'} className="rounded bg-gray-300 px-6 py-2 font-bold text-gray-700">戻る</Link></div>
        </form>
    </Layout>;
}
