<?php

namespace App\Http\Controllers\Web\Middleware;

use App\Models\Factory;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ShareWebContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('logout')) {
            abort_unless($request->user() && $request->user()->is_active !== false, 403, 'This account is inactive.');
        }
        $current = Factory::local();
        View::share(['currentFactory' => $current, 'availableFactories' => collect([$current]), 'unreadNotificationsCount' => $current?->notifications()->where('status', 'pending')->count() ?? 0]);

        return $next($request);
    }
}
