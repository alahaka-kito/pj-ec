import { Link, useForm } from '@inertiajs/react';
import { Layout, inputClass } from './Shared';

export default function Form({ shipperCode }) {
    const editing = Boolean(shipperCode);
    const form = useForm({
        hatsu_ninushi_code: shipperCode?.hatsu_ninushi_code ?? '',
        product_name: shipperCode?.product_name ?? '',
    });
    const csvForm = useForm({ csv_file: null });
    const manualErrorMessages = ['hatsu_ninushi_code', 'product_name']
        .map(name => form.errors[name])
        .filter(Boolean);

    const submit = event => {
        event.preventDefault();
        if (editing) form.put(`/shipper-code/${shipperCode.seq}`);
        else form.post('/shipper-code');
    };

    const importCsv = event => {
        event.preventDefault();
        csvForm.post('/shipper-code/import', { forceFormData: true });
    };

    return <Layout active={editing ? '' : 'create'}>
        {!editing && <form onSubmit={importCsv} encType="multipart/form-data" className="mb-6 max-w-3xl rounded border border-gray-200 bg-white p-6 text-sm shadow-sm">
            <h2 className="mb-2 text-base font-bold text-gray-800">CSV一括登録</h2>
            <p className="mb-4 text-gray-600">ファイル名を shippercode.csv とし、A列に発荷主コード、B列に商品名を入力してください。</p>
            <div className="flex flex-wrap items-end gap-3">
                <label className="block font-medium text-gray-700">CSVファイル<input type="file" accept=".csv,text/csv" onChange={event => csvForm.setData('csv_file', event.target.files[0] ?? null)} className="mt-1 block text-sm" /></label>
                <button disabled={csvForm.processing || !csvForm.data.csv_file} className="rounded bg-orange-500 px-6 py-2 font-bold text-white hover:bg-orange-600 disabled:opacity-50">{csvForm.processing ? '取り込み中…' : 'CSVを取り込む'}</button>
            </div>
            {csvForm.errors.csv_file && <p className="mt-2 whitespace-pre-line text-sm text-red-600">{csvForm.errors.csv_file}</p>}
        </form>}
        <form onSubmit={submit} className="max-w-3xl rounded border border-gray-200 bg-gray-50 p-6 text-sm shadow-sm">
            {manualErrorMessages.length > 0 && <div className="mb-5 rounded border-l-4 border-red-500 bg-red-100 p-4 text-red-700"><p className="font-bold">入力内容を確認してください。</p><ul className="mt-2 list-inside list-disc">{manualErrorMessages.map((error, index) => <li key={index}>{error}</li>)}</ul></div>}
            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label className="block font-medium text-gray-700">発荷主コード<span className="ml-1 text-xs text-red-500">※必須</span><input className={inputClass} maxLength="50" value={form.data.hatsu_ninushi_code} onChange={event => form.setData('hatsu_ninushi_code', event.target.value)} />{form.errors.hatsu_ninushi_code && <span className="mt-1 block text-xs text-red-600">{form.errors.hatsu_ninushi_code}</span>}</label>
                <label className="block font-medium text-gray-700">商品名<span className="ml-1 text-xs text-red-500">※必須</span><input className={inputClass} maxLength="255" value={form.data.product_name} onChange={event => form.setData('product_name', event.target.value)} />{form.errors.product_name && <span className="mt-1 block text-xs text-red-600">{form.errors.product_name}</span>}</label>
            </div>
            <div className="flex gap-4"><button disabled={form.processing} className="rounded bg-emerald-600 px-8 py-2 font-bold text-white hover:bg-emerald-700 disabled:opacity-50">{form.processing ? '送信中…' : editing ? '更新' : '登録'}</button><Link href="/shipper-code" className="rounded bg-gray-300 px-6 py-2 font-bold text-gray-700">戻る</Link></div>
        </form>
    </Layout>;
}
