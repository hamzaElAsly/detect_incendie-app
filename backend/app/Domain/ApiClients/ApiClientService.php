<?php

namespace App\Domain\ApiClients;

use App\Domain\Audit\AuditService;
use App\Models\ApiClient;
use App\Models\Factory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApiClientService
{
    public function __construct(private AuditService $audit) {}

    public function create(Factory $factory, array $attributes, ?User $actor = null): ApiClientCredentials
    {
        return DB::transaction(function () use ($factory, $attributes, $actor): ApiClientCredentials {
            $secret = Str::random(64);
            $client = new ApiClient;
            $client->forceFill([
                'factory_id' => $factory->getKey(),
                'name' => $attributes['name'],
                'client_id' => (string) Str::uuid(),
                'secret_hash' => hash('sha256', $secret),
                'status' => 'active',
                'allowed_ips' => $attributes['allowed_ips'] ?? null,
                'scopes' => $attributes['scopes'] ?? [],
                'expires_at' => $attributes['expires_at'] ?? null,
            ])->save();
            $this->audit->record('api_client.created', $client, [], $client->toArray(), $factory, $actor);

            return new ApiClientCredentials($client, $secret);
        });
    }

    public function regenerateSecret(ApiClient $client, ?User $actor = null): ApiClientCredentials
    {
        return DB::transaction(function () use ($client, $actor): ApiClientCredentials {
            $secret = Str::random(64);
            $client->forceFill(['secret_hash' => hash('sha256', $secret)])->save();
            $this->audit->record('api_client.secret_regenerated', $client, [], ['rotated_at' => now()->toIso8601String()], $client->factory, $actor);

            return new ApiClientCredentials($client->refresh(), $secret);
        });
    }

    public function setStatus(ApiClient $client, string $status, ?User $actor = null): ApiClient
    {
        abort_unless(in_array($status, ['active', 'inactive', 'revoked'], true), 422);
        $old = $client->status;
        $client->update(['status' => $status, 'revoked_at' => $status === 'revoked' ? now() : $client->revoked_at]);
        $this->audit->record('api_client.status_changed', $client, ['status' => $old], ['status' => $status], $client->factory, $actor);

        return $client->refresh();
    }
}
