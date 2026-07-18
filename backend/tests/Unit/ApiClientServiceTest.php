<?php

namespace Tests\Unit;

use App\Domain\ApiClients\ApiClientService;
use App\Models\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiClientServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_secret_is_returned_once_and_never_serialized_or_stored_in_plain_text(): void
    {
        $factory = Factory::create(['name' => 'Factory', 'slug' => 'factory']);
        $credentials = app(ApiClientService::class)->create($factory, ['name' => 'Python Agent']);

        $this->assertSame(64, strlen($credentials->secret));
        $this->assertSame(hash('sha256', $credentials->secret), $credentials->client->getRawOriginal('secret_hash'));
        $this->assertArrayNotHasKey('secret_hash', $credentials->client->toArray());
        $this->assertStringNotContainsString($credentials->secret, json_encode($credentials->client));

        $newCredentials = app(ApiClientService::class)->regenerateSecret($credentials->client);
        $this->assertNotSame($credentials->secret, $newCredentials->secret);
        $this->assertSame(hash('sha256', $newCredentials->secret), $newCredentials->client->getRawOriginal('secret_hash'));
    }
}
