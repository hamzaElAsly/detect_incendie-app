<?php

namespace App\Actions\Auth;

use App\Models\User;

class IssueApiToken
{
    public function execute(User $user, string $name = 'api'): string
    {
        $plain = bin2hex(random_bytes(32));
        $user->apiTokens()->create([
            'name' => $name,
            'token' => hash('sha256', $plain),
            'expires_at' => now()->addMinutes((int) config('auth.api_token_expiration', 10080)),
        ]);

        return $plain;
    }
}
