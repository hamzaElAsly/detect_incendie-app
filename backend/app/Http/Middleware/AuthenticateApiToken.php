<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();
        $token = $plain ? ApiToken::with('user')->where('token', hash('sha256', $plain))->first() : null;

        if (! $token || ($token->expires_at && $token->expires_at->isPast())) {
            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }
        if (! $token->user->is_active) {
            return new JsonResponse(['message' => 'This account is inactive.'], 403);
        }

        $token->forceFill(['last_used_at' => now()])->save();
        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
