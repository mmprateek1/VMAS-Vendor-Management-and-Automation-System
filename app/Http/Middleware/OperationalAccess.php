<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class OperationalAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user?->hasPermission('dashboard.view'), 403);
        if ($user->must_change_password) {
            return redirect()->route('security')->with('status', 'Choose a new password before continuing.');
        }
        if ($user->role->name === 'admin' && ! $user->two_factor_confirmed_at) {
            return redirect()->route('security')->with('status', 'Set up two-factor authentication to access administration.');
        }
        abort_if($user->isManagement() && ! $request->isMethodSafe(), 403, 'Management access is read-only.');

        return $next($request);
    }
}
