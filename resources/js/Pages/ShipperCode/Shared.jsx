import { Link } from '@inertiajs/react';

export const inputClass = 'mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500';

export function Layout({ children, active }) {
    return <main className="min-h-screen bg-white p-6 text-sm text-gray-900 antialiased">
        <header className="mb-4 flex items-baseline justify-between">
            <h1 className="text-2xl font-bold text-gray-800">発荷主コード</h1>
            <a href="/home" className="text-sm text-gray-600 hover:underline">ホームへ戻る</a>
        </header>
        <nav className="mb-6 flex items-end border-b-2 border-orange-500">
            <Link href="/shipper-code" className={`rounded-t border px-6 py-2 font-medium ${active === 'index' ? 'border-orange-500 border-b-0 bg-orange-500 text-white' : 'border-transparent text-blue-500 hover:bg-gray-50'}`}>発荷主コード一覧</Link>
            <Link href="/shipper-code/create" className={`rounded-t border px-6 py-2 font-medium ${active === 'create' ? 'border-orange-500 border-b-0 bg-orange-500 text-white' : 'border-transparent text-blue-500 hover:bg-gray-50'}`}>新規登録</Link>
        </nav>
        {children}
    </main>;
}
