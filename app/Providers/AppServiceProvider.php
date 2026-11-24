<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        foreach (['dashboard.view', 'users.manage', 'audit.view', 'settings.view', 'vendors.view', 'vendors.manage', 'vendors.activate', 'vendors.export'] as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }
        Event::listen(Login::class, function ($event) {
            DB::transaction(function () use ($event) {
                $event->user->forceFill(['last_login_at' => now()])->save();
                Audit::record('auth.login', 'user', $event->user->id, actor: $event->user->id);
            });
        });
        Event::listen(Logout::class, function ($event) {
            if ($event->user) {
                Audit::record('auth.logout', 'user', $event->user->id, actor: $event->user->id);
            }
        });
        foreach ([
            TwoFactorAuthenticationEnabled::class => 'account.mfa_started',
            TwoFactorAuthenticationConfirmed::class => 'account.mfa_confirmed',
            TwoFactorAuthenticationDisabled::class => 'account.mfa_disabled',
            RecoveryCodesGenerated::class => 'account.recovery_codes_generated',
        ] as $event => $action) {
            Event::listen($event, fn ($event) => Audit::record($action, 'user', $event->user->id, actor: $event->user->id));
        }
    }
}
