<?php

namespace App\Http\Controllers\Web\Middleware;

use App\Models\Factory;
use App\Services\FactoryContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ResolveWebFactoryContext
{
    public function __construct(private readonly FactoryContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user && $user->is_active, 403);
        $factory = Factory::local();
        abort_unless($factory instanceof Factory && $user->canAccessFactory($factory), 403, 'Aucune usine accessible.');
        $request->session()->put('factory_id', $factory->getKey());
        $this->context->set($factory);
        $request->attributes->set('factory', $factory);
        View::share(['currentFactory' => $factory, 'availableFactories' => collect([$factory]), 'unreadNotificationsCount' => $factory->notifications()->where('status', 'pending')->count()]);

        return $next($request);
    }
}
