<?php

use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\ResolveFactoryContext;
use App\Http\Controllers\Web\Middleware\ResolveWebFactoryContext;
use App\Http\Controllers\Web\Middleware\ShareWebContext;
use App\Http\Controllers\Web\Middleware\SetLocale;
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
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SetLocale::class]);
        $middleware->alias([
            'auth.api' => AuthenticateApiToken::class,
            'factory' => ResolveFactoryContext::class,
            'factory.web' => ResolveWebFactoryContext::class,
            'web.context' => ShareWebContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
