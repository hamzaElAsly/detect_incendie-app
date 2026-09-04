<?php

namespace Tests\Feature;

use App\Actions\Auth\IssueApiToken;
use App\Models\Factory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactoryContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_factory_is_resolved_without_header_and_foreign_header_is_forbidden(): void
    {
        $user = User::factory()->create();
        $own = Factory::create(['name' => 'Own', 'slug' => 'own']);
        $other = Factory::create(['name' => 'Other', 'slug' => 'other']);
        $own->users()->attach($user, ['role' => 'factory_admin']);
        $token = app(IssueApiToken::class)->execute($user);

        $this->withToken($token)->getJson('/api/factory-context')->assertOk()->assertJsonPath('data.id', $own->id);
        $this->withToken($token)->withHeader('X-Factory-ID', $other->id)->getJson('/api/factory-context')->assertForbidden();
        $this->withToken($token)->withHeader('X-Factory-ID', $own->id)->getJson('/api/factory-context')->assertOk()->assertJsonPath('data.id', $own->id);
    }
}
