<?php

namespace App\Providers;

use App\Authorization\AlertRecipientPolicy;
use App\Authorization\ApiClientPolicy;
use App\Http\Controllers\Web\Policies\FactoryWebPolicy;
use App\Http\Controllers\Web\Policies\RoleWebPolicy;
use App\Http\Controllers\Web\Policies\UserWebPolicy;
use App\Models\AlertRecipient;
use App\Models\ApiClient;
use App\Models\Factory;
use App\Models\Role;
use App\Models\User;
use App\Services\FactoryContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FactoryContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(fn ($user) => $user->is_super_admin ? true : null);
        foreach (['roles.view', 'roles.manage'] as $permission) {
            Gate::define($permission, fn ($user) => $user->hasPermission($permission, request()->attributes->get('factory')));
        }
        foreach (['factories.view', 'factories.create', 'factories.update', 'factories.delete', 'users.view', 'users.invite', 'users.update', 'users.remove', 'zones.view', 'zones.create', 'zones.update', 'zones.delete', 'cameras.view', 'cameras.create', 'cameras.update', 'cameras.delete', 'cameras.test', 'events.view', 'events.confirm', 'events.resolve', 'events.mark_false_alarm', 'events.delete', 'alerts.view', 'alerts.manage', 'notifications.view', 'notifications.manage', 'recipients.view', 'recipients.manage', 'api_clients.view', 'api_clients.manage', 'settings.view', 'settings.update', 'reports.view', 'plans.view', 'plans.manage', 'subscriptions.view', 'subscriptions.manage', 'audit_logs.view'] as $permission) {
            Gate::define($permission, fn ($user) => $user->hasPermission($permission, request()->attributes->get('factory')));
        }
        Gate::policy(AlertRecipient::class, AlertRecipientPolicy::class);
        Gate::policy(ApiClient::class, ApiClientPolicy::class);
        Gate::policy(Factory::class, FactoryWebPolicy::class);
        Gate::policy(User::class, UserWebPolicy::class);
        Gate::policy(Role::class, RoleWebPolicy::class);
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip().'|'.$request->input('email')));
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by((string) optional($request->user())->getAuthIdentifier() ?: $request->ip()));
        RateLimiter::for('edge', fn (Request $request) => Limit::perMinute(120)->by((string) $request->attributes->get('api_client')?->id ?: $request->ip()));
        RateLimiter::for('edge-detection', fn (Request $request) => Limit::perMinute(60)->by((string) $request->attributes->get('api_client')?->id ?: $request->ip()));
        RateLimiter::for('sensitive-write', fn (Request $request) => Limit::perMinute(20)->by((string) $request->user()?->id ?: $request->ip()));
        RateLimiter::for('secret-management', fn (Request $request) => Limit::perMinute(10)->by((string) $request->user()?->id ?: $request->ip()));
        RateLimiter::for('export', fn (Request $request) => Limit::perMinute(10)->by((string) $request->user()?->id ?: $request->ip()));
        RateLimiter::for('profile-upload', fn (Request $request) => Limit::perMinute(10)->by((string) $request->user()?->id ?: $request->ip()));
    }
}
