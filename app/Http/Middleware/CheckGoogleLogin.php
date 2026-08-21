<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckGoogleLogin
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check() || Auth::user()->login_type !== 'google') {
            
            return redirect('/home')->with('error', 'この機能は社内ログイン（Google）ユーザーのみ利用可能です。');
        }

        return $next($request);
    }
}