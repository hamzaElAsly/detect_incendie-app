<?php

namespace App\Http\Controllers\Web\Middleware;

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
        $factories = $request->user()->accessibleFactories();
        $current = $factories->firstWhere('id', $request->session()->get('factory_id')) ?? $factories->first();
        View::share(['currentFactory' => $current, 'availableFactories' => $factories, 'unreadNotificationsCount' => $current?->notifications()->where('status', 'pending')->count() ?? 0]);

        return $next($request);
    }
}
