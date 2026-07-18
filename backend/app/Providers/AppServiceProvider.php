<?php

namespace App\Providers;

use App\Authorization\AlertRecipientPolicy;
use App\Authorization\ApiClientPolicy;
use App\Models\AlertRecipient;
use App\Models\ApiClient;
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
        Gate::policy(AlertRecipient::class, AlertRecipientPolicy::class);
        Gate::policy(ApiClient::class, ApiClientPolicy::class);
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip().'|'.$request->input('email')));
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by((string) optional($request->user())->getAuthIdentifier() ?: $request->ip()));
    }
}
