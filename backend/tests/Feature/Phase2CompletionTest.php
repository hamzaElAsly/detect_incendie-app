<?php

namespace Tests\Feature;

use App\Domain\ApiClients\ApiClientService;
use App\Domain\Health\CameraStatusService;
use App\Models\Camera;
use App\Models\Factory;
use App\Models\FactorySetting;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase2CompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_super_admin_creates_a_global_user_with_hashed_password_role_and_audit(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Platform Operator', 'email' => ' OPERATOR@EXAMPLE.TEST ',
            'password' => 'Strong-password-123!', 'password_confirmation' => 'Strong-password-123!',
            'role' => 'platform_admin', 'is_active' => 1,
        ]);
        $user = User::where('email', 'operator@example.test')->firstOrFail();
        $response->assertRedirect(route('users.show', $user));
        $this->assertTrue(Hash::check('Strong-password-123!', $user->password));
        $this->assertTrue($user->hasRole('platform_admin'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created', 'entity_id' => (string) $user->id]);
    }

    public function test_non_platform_user_and_platform_admin_privilege_escalation_are_forbidden(): void
    {
        $viewer = User::factory()->create();
        $this->actingAs($viewer)->post(route('users.store'), [])->assertForbidden();
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'platform_admin')->firstOrFail(), ['factory_id' => null]);
        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Root', 'email' => 'root@example.test', 'password' => 'Strong-password-123!',
            'password_confirmation' => 'Strong-password-123!', 'role' => 'super_admin',
        ])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'root@example.test']);
    }

    public function test_edge_heartbeat_requires_valid_active_scoped_same_tenant_client(): void
    {
        $factory = Factory::create(['name' => 'Alpha', 'slug' => 'alpha']);
        $camera = Camera::forceCreate(['factory_id' => $factory->id, 'name' => 'Gate', 'reference' => 'G1', 'status' => 'offline']);
        $credentials = app(ApiClientService::class)->create($factory, ['name' => 'Agent', 'scopes' => ['camera:heartbeat']]);
        $token = $credentials->client->client_id.'.'.$credentials->secret;

        $this->postJson(route('edge.cameras.heartbeat', $camera), ['fps' => 15.5])->assertUnauthorized();
        $this->withToken($token)->postJson(route('edge.cameras.heartbeat', $camera), [
            'status' => 'online', 'fps' => 15.5, 'latency_ms' => 80, 'resolution' => '1920x1080',
            'last_frame_at' => now()->toIso8601String(), 'edge_agent_version' => '1.0.0',
        ])->assertOk()->assertJsonPath('data.status', 'online');
        $this->assertSame(15.5, (float) $camera->fresh()->fps);
        $this->assertNotNull($credentials->client->fresh()->last_used_at);

        $other = Factory::create(['name' => 'Other', 'slug' => 'other']);
        $foreign = Camera::forceCreate(['factory_id' => $other->id, 'name' => 'Foreign', 'reference' => 'F1', 'status' => 'offline']);
        $this->withToken($token)->postJson(route('edge.cameras.heartbeat', $foreign), [])->assertForbidden();
    }

    public function test_edge_auth_rejects_missing_scope_expired_revoked_and_rotated_secrets(): void
    {
        $factory = Factory::create(['name' => 'Alpha', 'slug' => 'alpha']);
        $camera = Camera::forceCreate(['factory_id' => $factory->id, 'name' => 'Gate', 'reference' => 'G1', 'status' => 'offline']);
        $service = app(ApiClientService::class);
        $unscoped = $service->create($factory, ['name' => 'Read', 'scopes' => ['camera:read']]);
        $this->withToken($unscoped->client->client_id.'.'.$unscoped->secret)->postJson(route('edge.cameras.heartbeat', $camera), [])->assertForbidden();
        $expired = $service->create($factory, ['name' => 'Old', 'scopes' => ['camera:heartbeat'], 'expires_at' => now()->subMinute()]);
        $this->withToken($expired->client->client_id.'.'.$expired->secret)->postJson(route('edge.cameras.heartbeat', $camera), [])->assertForbidden();
        $valid = $service->create($factory, ['name' => 'Rotate', 'scopes' => ['camera:heartbeat']]);
        $oldToken = $valid->client->client_id.'.'.$valid->secret;
        $new = $service->regenerateSecret($valid->client);
        $this->withToken($oldToken)->postJson(route('edge.cameras.heartbeat', $camera), [])->assertUnauthorized();
        $service->setStatus($new->client, 'revoked');
        $this->withToken($new->client->client_id.'.'.$new->secret)->postJson(route('edge.cameras.heartbeat', $camera), [])->assertForbidden();
    }

    public function test_camera_status_uses_unknown_online_offline_disabled_and_factory_threshold(): void
    {
        $factory = Factory::create(['name' => 'Alpha', 'slug' => 'alpha']);
        $camera = Camera::forceCreate(['factory_id' => $factory->id, 'name' => 'Gate', 'reference' => 'G1', 'status' => 'offline']);
        $service = app(CameraStatusService::class);
        $this->assertSame('unknown', $service->status($camera));
        $camera->update(['last_heartbeat_at' => now()->subSeconds(60)]);
        $this->assertSame('online', $service->status($camera->refresh()));
        FactorySetting::forceCreate(['factory_id' => $factory->id, 'key' => 'camera_offline_threshold_seconds', 'value' => 30, 'type' => 'integer']);
        $this->assertSame('offline', $service->status($camera->refresh()));
        $camera->update(['is_active' => false]);
        $this->assertSame('disabled', $service->status($camera->refresh()));
    }

    public function test_profile_validates_and_persists_avatar_timezone_and_preferences(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name, 'email' => $user->email, 'phone' => null,
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
            'timezone' => 'Africa/Casablanca',
            'preferences' => ['theme' => 'dark', 'locale' => 'fr', 'compact_sidebar' => 1, 'email_notifications' => 1],
        ])->assertSessionHasNoErrors();
        $user->refresh();
        Storage::disk('public')->assertExists($user->avatar_path);
        $this->assertSame('Africa/Casablanca', $user->timezone);
        $this->assertSame('dark', $user->preferences['theme']);
        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name, 'email' => $user->email, 'timezone' => 'Not/AZone',
            'preferences' => ['theme' => 'dark', 'locale' => 'fr', 'role' => 'super_admin'],
        ])->assertSessionHasErrors(['timezone', 'preferences']);
        $this->assertFalse($user->fresh()->is_super_admin);
    }
}
