import { Link, useForm } from '@inertiajs/react';
import { inputClass, Layout, sizes } from './Shared';

const blankVariation = () => ({ variation_name: '', shipping_size: '', cool_delivery_service: false, time_delivery_service: false, products: [{ product_management_code: '', quantity: 1 }] });

export default function Form({ salesMaster, productMasters = [] }) {
    const editing = Boolean(salesMaster);
    const initialVariations = (salesMaster?.variations ?? []).map(variation => ({
        variation_name: variation.variation_name ?? '', shipping_size: variation.shipping_size ?? '',
        cool_delivery_service: variation.cool_delivery_service == 1, time_delivery_service: variation.time_delivery_service == 1,
        products: (variation.products ?? []).map(product => ({ product_management_code: product.product_management_code, quantity: product.quantity })),
    }));
    const form = useForm({ product_name: salesMaster?.product_name ?? '', amazon_url: salesMaster?.amazon_url ?? '', tiktok_url: salesMaster?.tiktok_url ?? '', drive_path: salesMaster?.drive_path ?? '', variations: initialVariations.length ? initialVariations : [blankVariation()] });
    const setVariation = (variationIndex, key, value) => form.setData('variations', form.data.variations.map((variation, index) => index === variationIndex ? { ...variation, [key]: value } : variation));
    const setProduct = (variationIndex, productIndex, key, value) => form.setData('variations', form.data.variations.map((variation, index) => index === variationIndex ? { ...variation, products: variation.products.map((product, index) => index === productIndex ? { ...product, [key]: value } : product) } : variation));
    const submit = event => {
        event.preventDefault();
        if (!window.confirm(editing ? '販売マスタを更新しますか？' : '販売マスタを登録しますか？')) return;
        if (editing) form.put(`/sales-master/${salesMaster.id}`);
        else form.post('/sales-master');
    };
    const errorFor = path => form.errors[path];
    return <Layout active={editing ? '' : 'create'}>
        <form onSubmit={submit} className="max-w-5xl">
            {Object.keys(form.errors).length > 0 && <div className="mb-6 rounded border-l-4 border-red-500 bg-red-50 p-4 text-xs text-red-700"><p className="mb-1 font-bold">入力内容を確認してください。</p>{Object.values(form.errors).map((error, index) => <div key={index}>{error}</div>)}</div>}
            <div className="mb-8 overflow-hidden rounded-sm border border-gray-200 bg-white shadow-sm"><table className="w-full table-fixed border-collapse text-left"><tbody>
                {[["product_name", '販売名', 'text', true], ['amazon_url', 'Amazon販売ページURL', 'url'], ['tiktok_url', 'TikTok販売ページURL', 'url'], ['drive_path', 'ドライブパス', 'text']].map(([name, label, type, required]) => <tr key={name} className="border-b last:border-0"><th className="w-1/4 border-r bg-gray-50 p-4 font-medium text-gray-700">{label}{required && <span className="text-red-500"> *</span>}</th><td className="p-4"><input type={type} required={required} value={form.data[name]} onChange={event => form.setData(name, event.target.value)} className={`${inputClass} ${type === 'url' || name === 'drive_path' ? 'font-mono text-xs' : ''}`} />{errorFor(name) && <p className="mt-1 text-xs text-red-600">{errorFor(name)}</p>}</td></tr>)}
            </tbody></table></div>
            <div className="mb-6 flex items-center justify-between"><h2 className="text-lg font-bold text-gray-800">バリエーション設定</h2><button type="button" onClick={() => form.setData('variations', [...form.data.variations, blankVariation()])} className="rounded bg-blue-600 px-4 py-1.5 text-xs font-bold text-white hover:bg-blue-700">＋ バリエーションを追加</button></div>
            <datalist id="product-master-list">{productMasters.map(product => <option key={product.product_management_code} value={product.product_management_code}>{product.supplier_product_name}（{product.product_management_code}）</option>)}</datalist>
            <div className="mb-8 space-y-6">{form.data.variations.map((variation, vIndex) => <section key={vIndex} className="relative rounded border border-gray-300 bg-gray-50/50 p-4 shadow-sm">
                <div className="mb-3 flex items-center justify-between border-b border-gray-200 pb-2"><h3 className="text-sm font-bold text-gray-700">バリエーション {vIndex + 1}</h3><button type="button" onClick={() => form.setData('variations', form.data.variations.filter((_, index) => index !== vIndex))} className="text-xs font-bold text-red-600 hover:text-red-700">削除</button></div>
                <div className="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <label className="block text-xs font-bold text-gray-600">バリエーション名 <span className="text-red-500">*</span><input required value={variation.variation_name} onChange={event => setVariation(vIndex, 'variation_name', event.target.value)} placeholder="例：2本セット" className={`${inputClass} mt-1 bg-white font-normal`} />{errorFor(`variations.${vIndex}.variation_name`) && <span className="text-red-600">{errorFor(`variations.${vIndex}.variation_name`)}</span>}</label>
                    <label className="block text-xs font-bold text-gray-600">配送サイズ<select value={variation.shipping_size} onChange={event => setVariation(vIndex, 'shipping_size', event.target.value)} className={`${inputClass} mt-1 bg-white font-normal`}><option value="">指定なし</option>{sizes.map(size => <option key={size} value={size}>{size}サイズ</option>)}</select>{errorFor(`variations.${vIndex}.shipping_size`) && <span className="text-red-600">{errorFor(`variations.${vIndex}.shipping_size`)}</span>}</label>
                    <div className="flex items-center gap-6 pt-2"><label className="flex cursor-pointer items-center gap-2"><input type="checkbox" checked={Boolean(variation.cool_delivery_service)} onChange={event => setVariation(vIndex, 'cool_delivery_service', event.target.checked)} className="rounded border-gray-300 text-orange-500 focus:ring-orange-500" />クール宅急便</label><label className="flex cursor-pointer items-center gap-2"><input type="checkbox" checked={Boolean(variation.time_delivery_service)} onChange={event => setVariation(vIndex, 'time_delivery_service', event.target.checked)} className="rounded border-gray-300 text-orange-500 focus:ring-orange-500" />時間帯指定サービス</label></div>
                </div>
                <div className="rounded border border-gray-200 bg-white p-3"><div className="mb-2 flex items-center justify-between"><span className="text-xs font-bold text-gray-600">対象商品と使用数量</span><button type="button" onClick={() => setVariation(vIndex, 'products', [...variation.products, { product_management_code: '', quantity: 1 }])} className="text-xs font-bold text-blue-600 hover:underline">＋ 商品追加</button></div>
                    <div className="space-y-2">{variation.products.map((product, pIndex) => <div key={pIndex} className="flex flex-wrap items-center gap-2"><label className="sr-only" htmlFor={`code-${vIndex}-${pIndex}`}>商品コード</label><input id={`code-${vIndex}-${pIndex}`} list="product-master-list" value={product.product_management_code} onChange={event => setProduct(vIndex, pIndex, 'product_management_code', event.target.value)} placeholder="商品名またはコードで検索" required className="w-full rounded border border-gray-300 px-2 py-1 text-xs font-mono md:w-1/2" /><input aria-label="使用数量" type="number" min="1" value={product.quantity} onChange={event => setProduct(vIndex, pIndex, 'quantity', event.target.value)} required className="w-28 rounded border border-gray-300 px-2 py-1 text-xs font-mono" /><span className="text-xs text-gray-500">在庫数：{productMasters.find(item => item.product_management_code === product.product_management_code)?.stock ?? '－'}</span><button type="button" onClick={() => setVariation(vIndex, 'products', variation.products.filter((_, index) => index !== pIndex))} className="px-2 text-xs font-bold text-red-500">削除</button>{errorFor(`variations.${vIndex}.products.${pIndex}.product_management_code`) && <span className="text-xs text-red-600">{errorFor(`variations.${vIndex}.products.${pIndex}.product_management_code`)}</span>}{errorFor(`variations.${vIndex}.products.${pIndex}.quantity`) && <span className="text-xs text-red-600">{errorFor(`variations.${vIndex}.products.${pIndex}.quantity`)}</span>}</div>)}</div>
                </div>
            </section>)}</div>
            <div className="flex gap-3"><button disabled={form.processing} className="rounded bg-orange-500 px-10 py-2 font-bold tracking-wide text-white hover:bg-orange-600 disabled:opacity-50">{form.processing ? '送信中…' : editing ? '更新' : '登録'}</button><Link href="/sales-master" className="rounded bg-slate-300 px-10 py-2 font-bold text-slate-700">戻る</Link></div>
        </form>
    </Layout>;
}
