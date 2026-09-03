<x-app-layout>
    <div class="text-gray-900 antialiased bg-white min-h-screen text-sm">
        
        <!-- エラーメッセージの表示（直叩きでホームに戻された時用） -->
        @if (session('error'))
            <div class="p-6 pb-0">
                <div class="p-4 bg-red-100 border-l-4 border-red-500 text-red-700 font-bold rounded shadow-sm text-xs">
                    {{ session('error') }}
                </div>
            </div>
        @endif

        <!-- 一体型上部ヘッダー -->
        <div class="-mt-6 -mx-6 bg-gray-100/80 px-8 py-16 border-b border-gray-200 mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 tracking-wider">ECツール</h1>
                </div>
                
                <div class="flex flex-col items-end gap-2">
                    @if(Auth::user())
                        <!-- 1. ログアウトフォーム（上） -->
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-xs text-gray-600 hover:text-gray-900 underline font-medium">
                                ログアウト
                            </button>
                        </form>

                        <!-- 2. ログインユーザー表示（下） -->
                        <div class="text-xs text-gray-500 bg-white px-3 py-1.5 rounded-full border border-gray-200">
                            ログインユーザー: <span class="font-bold text-gray-700">{{ Auth::user()->name ?? Auth::user()->email }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- メインコンテンツ領域 -->
        <div class="px-2">
            @if(Auth::user())
                
                <!-- 機能セクション（全ユーザー表示） -->
                <div class="mb-8">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-1.5 h-5 bg-orange-500 rounded-full"></div>
                        <h2 class="text-base font-bold text-gray-800 tracking-wide">機能</h2>
                    </div>

                    <div class="flex flex-wrap gap-4">
                        <!-- 出荷指示データ（Googleログインユーザーのみ表示） -->
                        @if(Auth::user()->login_type === 'google')
                            <a href="{{ route('shipping-instruction.index') }}" 
                               class="group w-44 p-4 bg-white border border-orange-200 hover:border-orange-500 rounded-lg shadow-sm hover:shadow-md transition-all duration-200 flex items-center justify-center relative overflow-hidden">
                                <div class="absolute top-0 left-0 w-1 h-full bg-orange-500"></div>
                                <span class="text-base font-bold text-gray-800 group-hover:text-orange-600 transition-colors">
                                    出荷指示データ
                                </span>
                            </a>
                        @endif
                    </div>
                </div>

                <!-- マスタメンテナンスセクション（Googleログインユーザーのみ表示） -->
                @if(Auth::user()->login_type === 'google')
                    <div class="mb-8">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-1.5 h-5 bg-slate-600 rounded-full"></div>
                            <h2 class="text-base font-bold text-gray-800 tracking-wide">マスタメンテナンス</h2>
                        </div>

                        <div class="flex flex-wrap gap-4">
                            <!-- 仕入先マスタ -->
                            <a href="{{ route('supplier-master.index') }}" 
                               class="group w-44 p-4 bg-white border border-gray-200 hover:border-slate-400 rounded-lg shadow-sm hover:shadow-md transition-all duration-200 flex items-center justify-center relative overflow-hidden">
                                <div class="absolute top-0 left-0 w-1 h-full bg-slate-600"></div>
                                <span class="text-base font-bold text-gray-800 group-hover:text-slate-700 transition-colors">
                                    仕入先マスタ
                                </span>
                            </a>

                            <!-- 商品マスタ -->
                            <a href="{{ route('product-master.index') }}" 
                               class="group w-44 p-4 bg-white border border-gray-200 hover:border-slate-400 rounded-lg shadow-sm hover:shadow-md transition-all duration-200 flex items-center justify-center relative overflow-hidden">
                                <div class="absolute top-0 left-0 w-1 h-full bg-slate-600"></div>
                                <span class="text-base font-bold text-gray-800 group-hover:text-slate-700 transition-colors">
                                    商品マスタ
                                </span>
                            </a>

                            <!-- 販売マスタ -->
                            <a href="{{ route('sales-master.index') }}" 
                               class="group w-44 p-4 bg-white border border-gray-200 hover:border-slate-400 rounded-lg shadow-sm hover:shadow-md transition-all duration-200 flex items-center justify-center relative overflow-hidden">
                                <div class="absolute top-0 left-0 w-1 h-full bg-slate-600"></div>
                                <span class="text-base font-bold text-gray-800 group-hover:text-slate-700 transition-colors">
                                    販売マスタ
                                </span>
                            </a>
                        </div>
                    </div>
                @endif

            @endif
        </div>
    </div>
</x-app-layout>