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
        $factory = Factory::local();
        $headerId = $request->header('X-Factory-ID');
        $routeFactory = $request->route('factory');

        if ($headerId && $headerId !== $factory->getKey()) {
            return new JsonResponse(['message' => 'Factory does not match this local installation.'], 403);
        }

        if ($routeFactory instanceof Factory && $routeFactory->isNot($factory)) {
            return new JsonResponse(['message' => 'Factory does not match this local installation.'], 403);
        }

        if (is_string($routeFactory) && $routeFactory !== $factory->getKey()) {
            return new JsonResponse(['message' => 'Factory does not match this local installation.'], 403);
        }

        if (! $factory->is_active) {
            return new JsonResponse(['message' => 'This factory is inactive.'], 403);
        }

        $user = $request->user();
        if (! $user?->canAccessFactory($factory)) {
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
