import { Link, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import { fieldClass, Layout } from './Shared';

const sizes = [60, 80, 100, 120, 140, 160, 180, 200];

export default function Form({ product, suppliers = [] }) {
    const editing = Boolean(product);
    const form = useForm({
        image: null,
        management_code: product?.management_code ?? '',
        product_management_code: product?.product_management_code ?? '',
        supplier_product_name: product?.supplier_product_name ?? '',
        buying_price: product?.buying_price ?? '', stock: product?.stock ?? 0, size: product?.size ?? '',
        cool_delivery_service: Boolean(product?.cool_delivery_service), time_delivery_service: Boolean(product?.time_delivery_service),
        selling_places: product?.selling_places ?? [], drive_path: product?.drive_path ?? '',
    });
    const selectedSupplier = suppliers.find(supplier => supplier.management_code === form.data.management_code);
    const sellingPlaces = useMemo(() => selectedSupplier?.selling_places ?? [], [selectedSupplier]);
    const submit = event => {
        event.preventDefault();
        if (!window.confirm(editing ? 'この内容で更新しますか？' : 'この内容で登録しますか？')) return;
        if (editing) {
            form.transform(data => ({ ...data, _method: 'put' }));
            form.post(`/product-master/${product.seq}`, { forceFormData: true });
        }
        else form.post('/product-master', { forceFormData: true });
    };
    const textField = (name, label, props = {}) => <label className="block text-xs font-medium text-gray-700">{label}{props.required && <span className="text-red-500"> ※必須</span>}<input {...props} type={props.type ?? 'text'} name={name} value={form.data[name] ?? ''} onChange={event => form.setData(name, event.target.value)} className={fieldClass} />{form.errors[name] && <span className="mt-1 block text-red-600">{form.errors[name]}</span>}</label>;
    return <Layout active={editing ? '' : 'create'}>
        <form onSubmit={submit} encType="multipart/form-data" className="max-w-4xl space-y-5">
            {Object.keys(form.errors).length > 0 && <div className="rounded border-l-4 border-red-500 bg-red-50 p-4 text-sm text-red-700">入力内容を確認してください。{Object.values(form.errors).map((error, i) => <div key={i}>{error}</div>)}</div>}
            <div className="overflow-hidden rounded border border-gray-200 shadow-sm"><table className="w-full border-collapse text-left text-sm"><tbody>
                <tr className="border-b"><th className="w-1/3 border-r bg-gray-50 p-3">商品画像</th><td className="p-3"><input type="file" accept="image/*" onChange={event => form.setData('image', event.target.files[0] ?? null)} className="text-xs file:mr-4 file:rounded file:border-0 file:bg-orange-50 file:px-4 file:py-1.5 file:font-semibold file:text-orange-700 hover:file:bg-orange-100" />{product?.image_path && <img src={product.image_path} alt="現在の商品画像" className="mt-3 h-20 w-20 rounded border object-cover" />}{form.errors.image && <p className="mt-1 text-xs text-red-600">{form.errors.image}</p>}</td></tr>
                <tr className="border-b"><th className="border-r bg-gray-50 p-3">仕入先管理コード</th><td className="p-3"><select required value={form.data.management_code} onChange={event => { form.setData('management_code', event.target.value); form.setData('selling_places', []); }} className={`${fieldClass} max-w-md`}><option value="">選択してください</option>{suppliers.map(supplier => <option key={supplier.management_code} value={supplier.management_code}>{supplier.management_code}：{supplier.company_name}</option>)}</select>{form.errors.management_code && <p className="mt-1 text-xs text-red-600">{form.errors.management_code}</p>}</td></tr>
                <tr className="border-b"><th className="border-r bg-gray-50 p-3">商品管理コード</th><td className="p-3">{textField('product_management_code', '', { required: true, maxLength: 50 })}</td></tr>
                <tr className="border-b"><th className="border-r bg-gray-50 p-3">仕入先商品名</th><td className="p-3">{textField('supplier_product_name', '', { required: true, maxLength: 255 })}</td></tr>
                <tr className="border-b"><th className="border-r bg-gray-50 p-3">仕入価格</th><td className="p-3">{textField('buying_price', '', { type: 'number', min: 0, required: true })}{form.errors.buying_price && <p className="text-xs text-red-600">{form.errors.buying_price}</p>}</td></tr>
                <tr className="border-b"><th className="border-r bg-gray-50 p-3">在庫数</th><td className="p-3">{textField('stock', '', { type: 'number', min: 0 })}{form.errors.stock && <p className="text-xs text-red-600">{form.errors.stock}</p>}</td></tr>
                <tr className="border-b"><th className="border-r bg-gray-50 p-3">商品サイズ</th><td className="p-3"><select required value={form.data.size} onChange={event => form.setData('size', event.target.value)} className={`${fieldClass} w-40`}><option value="">選択してください</option>{sizes.map(size => <option key={size} value={size}>{size}サイズ</option>)}</select>{form.errors.size && <p className="mt-1 text-xs text-red-600">{form.errors.size}</p>}</td></tr>
                {[["cool_delivery_service", 'クール宅急便'], ["time_delivery_service", '時間帯指定サービス']].map(([name, label]) => <tr key={name} className="border-b"><th className="border-r bg-gray-50 p-3">{label}</th><td className="p-3"><label className="inline-flex cursor-pointer items-center gap-2"><input type="checkbox" checked={form.data[name]} onChange={event => form.setData(name, event.target.checked)} className="rounded border-gray-300 text-orange-500 focus:ring-orange-500" />使用する</label></td></tr>)}
                <tr className="border-b"><th className="border-r bg-gray-50 p-3">販売先</th><td className="p-3"><div className="flex flex-wrap gap-4">{sellingPlaces.length ? sellingPlaces.map(place => <label key={place} className="inline-flex cursor-pointer items-center gap-2"><input type="checkbox" checked={form.data.selling_places.includes(place)} onChange={event => form.setData('selling_places', event.target.checked ? [...form.data.selling_places, place] : form.data.selling_places.filter(value => value !== place))} className="rounded border-gray-300 text-orange-500 focus:ring-orange-500" />{place}</label>) : <span className="text-xs text-gray-400">仕入先を選択すると販売先を表示します。</span>}</div>{form.errors.selling_places && <p className="mt-1 text-xs text-red-600">{form.errors.selling_places}</p>}</td></tr>
                <tr><th className="border-r bg-gray-50 p-3">ドライブパス</th><td className="p-3">{textField('drive_path', '', { type: 'url', placeholder: 'https://drive.google.com/...' })}{form.errors.drive_path && <p className="text-xs text-red-600">{form.errors.drive_path}</p>}</td></tr>
            </tbody></table></div>
            <div className="flex gap-3"><button disabled={form.processing} className="rounded bg-orange-500 px-10 py-2 font-bold text-white hover:bg-orange-600 disabled:opacity-50">{form.processing ? '送信中…' : editing ? '更新' : '登録'}</button><Link href={editing ? `/product-master/${product.seq}` : '/product-master'} className="rounded bg-slate-300 px-10 py-2 font-bold text-slate-700">{editing ? 'キャンセル' : '戻る'}</Link></div>
        </form>
    </Layout>;
}
