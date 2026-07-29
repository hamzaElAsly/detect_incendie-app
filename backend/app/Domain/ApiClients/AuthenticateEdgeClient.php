<?php

namespace App\Domain\ApiClients;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateEdgeClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $parts = explode('.', (string) $request->bearerToken(), 2);
        $client = count($parts) === 2 ? ApiClient::with('factory')->where('client_id', $parts[0])->first() : null;
        if (! $client || ! hash_equals($client->getRawOriginal('secret_hash'), hash('sha256', $parts[1] ?? ''))) {
            return new JsonResponse(['message' => __('phase2.api.unauthenticated')], 401);
        }
        if ($client->statusLabel() !== 'active' || ! $client->factory?->is_active) {
            return new JsonResponse(['message' => __('phase2.api.client_unavailable')], 403);
        }
        if ($client->allowed_ips && ! in_array($request->ip(), $client->allowed_ips, true)) {
            return new JsonResponse(['message' => __('phase2.api.ip_forbidden')], 403);
        }
        $client->forceFill(['last_used_at' => now(), 'last_used_ip' => $request->ip()])->save();
        $request->attributes->set('api_client', $client);

        return $next($request);
    }
}
