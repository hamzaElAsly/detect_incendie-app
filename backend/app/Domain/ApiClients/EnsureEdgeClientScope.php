<?php

namespace App\Domain\ApiClients;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEdgeClientScope
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        if (! $request->attributes->get('api_client')?->hasScope($scope)) {
            return new JsonResponse(['message' => __('phase2.api.scope_forbidden')], 403);
        }

        return $next($request);
    }
}
