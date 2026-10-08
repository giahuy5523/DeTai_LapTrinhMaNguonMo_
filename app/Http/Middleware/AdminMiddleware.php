<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle($request, Closure $next) {
        if (Auth::check() && Auth::user()->role === 'admin') {
            return $next($request);
        }
        return redirect('/login')->withErrors(['permission' => 'Bạn không có quyền truy cập']);
    }

}
