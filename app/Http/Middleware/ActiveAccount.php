<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActiveAccount
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->session()->has('login.id')) {
            $challenged = User::find($request->session()->get('login.id'));
            if (! $challenged?->is_active || ! $challenged?->role_id) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors(['email' => 'This account is unavailable. Contact your administrator.']);
            }
        }
        if ($request->user() && (! $request->user()->is_active || ! $request->user()->role_id)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'This account is unavailable. Contact your administrator.']);
        }

        return $next($request);
    }
}
