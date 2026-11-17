<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class CustomerMiddleware
{
    public function handle($request, Closure $next)
    {
        if (Auth::check() && Auth::user()->role === 'customer') {
            return $next($request);
        }
        return redirect('/')->with('error', 'Chỉ khách hàng mới được truy cập chức năng này.');
    }
}
