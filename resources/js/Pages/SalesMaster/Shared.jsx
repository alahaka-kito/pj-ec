import { Link } from '@inertiajs/react';

export function Layout({ children, active }) {
    return <main className="min-h-screen bg-white p-6 text-sm text-gray-900 antialiased">
        <header className="mb-4 flex items-center justify-between"><h1 className="text-2xl font-bold tracking-wide text-gray-800">販売マスタ</h1><a href="/home" className="text-xs text-gray-500 hover:underline">ホームへ戻る</a></header>
        <nav className="mb-6 flex items-end border-b border-orange-500">
            <Link href="/sales-master" className={`rounded-t border px-8 py-2 font-medium ${active === 'index' ? 'border-orange-500 border-b-0 bg-orange-500 font-bold text-white' : 'border-transparent text-blue-500 hover:bg-gray-50'}`}>販売一覧</Link>
            <Link href="/sales-master/create" className={`rounded-t border px-8 py-2 font-medium ${active === 'create' ? 'border-orange-500 border-b-0 bg-orange-500 font-bold text-white' : 'border-transparent text-blue-500 hover:bg-gray-50'}`}>販売新規登録</Link>
        </nav>{children}
    </main>;
}

export const inputClass = 'w-full rounded border border-gray-300 px-3 py-1.5 focus:border-orange-500 focus:ring-1 focus:ring-orange-500';
export const sizes = [60, 80, 100, 120, 140, 160, 180, 200];
