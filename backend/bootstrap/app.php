<?php

use App\Domain\ApiClients\AuthenticateEdgeClient;
use App\Domain\ApiClients\EnsureEdgeClientScope;
use App\Domain\Health\CameraHeartbeatController;
use App\Domain\Security\AddSecurityHeaders;
use App\Domain\Users\GlobalUserController;
use App\Http\Controllers\Web\Middleware\ResolveWebFactoryContext;
use App\Http\Controllers\Web\Middleware\SetLocale;
use App\Http\Controllers\Web\Middleware\ShareWebContext;
use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\ResolveFactoryContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/platform.php'));
            Route::middleware(['api', 'auth.edge', 'edge.scope:camera:heartbeat', 'throttle:edge'])
                ->prefix('api/edge')
                ->post('cameras/{camera}/heartbeat', CameraHeartbeatController::class)
                ->name('edge.cameras.heartbeat');
            Route::middleware(['web', 'auth', 'verified', 'web.context'])->group(function (): void {
                Route::get('users/create', [GlobalUserController::class, 'create'])->name('users.create');
                Route::post('users', [GlobalUserController::class, 'store'])->middleware('throttle:sensitive-write')->name('users.store');
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SetLocale::class, AddSecurityHeaders::class]);
        $middleware->api(append: [AddSecurityHeaders::class]);
        $middleware->alias([
            'auth.api' => AuthenticateApiToken::class,
            'factory' => ResolveFactoryContext::class,
            'factory.web' => ResolveWebFactoryContext::class,
            'web.context' => ShareWebContext::class,
            'auth.edge' => AuthenticateEdgeClient::class,
            'edge.scope' => EnsureEdgeClientScope::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
