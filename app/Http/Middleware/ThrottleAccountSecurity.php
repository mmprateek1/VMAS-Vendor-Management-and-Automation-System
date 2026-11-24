<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ThrottleAccountSecurity
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->isMethodSafe() && $request->is('forgot-password', 'reset-password', 'user/confirm-password', 'user/password')) {
            $key = 'account-security:'.$request->path().'|'.$request->ip();
            if (RateLimiter::tooManyAttempts($key, 10)) {
                return response('Too many attempts. Please try again in one minute.', 429)->header('Retry-After', '60');
            }
            RateLimiter::hit($key, 60);
        }

        return $next($request);
    }
}
