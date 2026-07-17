<?php

namespace Tests\Feature;

use App\Models\Factory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_owner_factory_and_hashed_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Factory Owner', 'email' => 'owner@example.com', 'password' => 'VerySecure!123', 'password_confirmation' => 'VerySecure!123', 'factory_name' => 'Atlas Plant',
        ])->assertCreated()->assertJsonStructure(['data' => ['id', 'email', 'factories'], 'factory_id', 'token', 'token_type']);

        $user = User::whereEmail('owner@example.com')->firstOrFail();
        $factory = Factory::firstOrFail();
        $this->assertDatabaseHas('factory_user', ['factory_id' => $factory->id, 'user_id' => $user->id, 'role' => 'factory_owner']);
        $this->assertNotSame($response->json('token'), $user->apiTokens()->firstOrFail()->token);
    }

    public function test_login_me_refresh_and_logout_lifecycle(): void
    {
        $user = User::factory()->create(['password' => 'VerySecure!123']);
        $token = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'VerySecure!123'])->assertOk()->json('token');
        $this->withToken($token)->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.email', $user->email);
        $newToken = $this->withToken($token)->postJson('/api/auth/refresh')->assertOk()->json('token');
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
        $this->withToken($newToken)->postJson('/api/auth/logout')->assertOk();
        $this->withToken($newToken)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create(['password' => 'VerySecure!123', 'is_active' => false]);
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'VerySecure!123'])->assertForbidden();
    }
}
