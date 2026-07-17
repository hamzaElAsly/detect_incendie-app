<?php

namespace App\Http\Middleware;

use App\Models\Factory;
use App\Services\FactoryContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveFactoryContext
{
    public function __construct(private FactoryContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->header('X-Factory-ID');
        if (! $id) {
            return new JsonResponse(['message' => 'The X-Factory-ID header is required.'], 400);
        }

        $factory = Factory::find($id);
        if (! $factory) {
            return new JsonResponse(['message' => 'Factory not found.'], 404);
        }
        if (! $factory->is_active) {
            return new JsonResponse(['message' => 'This factory is inactive.'], 403);
        }

        $user = $request->user();
        $isSuperAdmin = (bool) ($user->is_super_admin ?? false);
        if (! $isSuperAdmin && ! $user->belongsToFactory($factory)) {
            return new JsonResponse(['message' => 'You do not belong to this factory.'], 403);
        }

        $this->context->set($factory);
        try {
            return $next($request);
        } finally {
            $this->context->clear();
        }
    }
}
