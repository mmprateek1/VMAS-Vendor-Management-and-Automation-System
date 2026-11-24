<?php

namespace App\Providers;

use App\Actions\ResetPassword;
use App\Actions\UpdatePassword;
use App\Http\Responses\ResetLinkResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, ResetLinkResponse::class);
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, ResetLinkResponse::class);
    }

    public function boot(): void
    {
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));
        Fortify::resetUserPasswordsUsing(ResetPassword::class);
        Fortify::updateUserPasswordsUsing(UpdatePassword::class);
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', Str::lower($request->string('email')->trim()->toString()))->first();
            // Always perform a hash check to reduce account enumeration through timing.
            $valid = Hash::check($request->input('password', ''), $user?->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');

            return $valid && $user?->is_active && $user?->role_id ? $user : null;
        });
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(Str::lower($request->input('email', '')).'|'.$request->ip()),
            Limit::perMinute(30)->by($request->ip()),
        ]);
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id').'|'.$request->ip()));
    }
}
