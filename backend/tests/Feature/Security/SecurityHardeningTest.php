<?php

namespace Tests\Feature\Security;

use App\Actions\Auth\IssueApiToken;
use App\Domain\ApiClients\ApiClientService;
use App\Models\Alert;
use App\Models\Camera;
use App\Models\Factory;
use App\Models\FireEvent;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_deactivated_user_is_blocked_from_global_routes_but_can_logout(): void
    {
        $user = User::factory()->create(['is_active' => false, 'email_verified_at' => now()]);
        $user->roles()->attach(Role::where('name', 'platform_admin')->firstOrFail(), ['factory_id' => null]);

        $this->actingAs($user)->get(route('users.index'))->assertForbidden();
        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_security_headers_are_present_on_web_responses(): void
    {
        $this->get(route('login'))
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy-Report-Only');
    }

    public function test_edge_rejects_unknown_and_sensitive_payload_fields(): void
    {
        $factory = Factory::create(['name' => 'A', 'slug' => 'a']);
        $camera = Camera::forceCreate(['factory_id' => $factory->id, 'name' => 'Camera', 'reference' => 'A1', 'status' => 'offline']);
        $credentials = app(ApiClientService::class)->create($factory, ['name' => 'Agent', 'scopes' => ['camera:heartbeat']]);

        $this->withToken($credentials->client->client_id.'.'.$credentials->secret)
            ->postJson(route('edge.cameras.heartbeat', $camera), [
                'fps' => 10, 'factory_id' => 'forged', 'is_active' => false, 'secret_hash' => 'forged',
            ])->assertUnprocessable()->assertJsonValidationErrors('payload');

        $this->assertTrue($camera->fresh()->is_active);
        $this->assertNull($camera->fresh()->last_heartbeat_at);
    }

    public function test_stored_xss_is_escaped_for_factory_alert_and_edge_error(): void
    {
        $payload = '<img src=x onerror=alert(1)>';
        $admin = User::factory()->create(['is_super_admin' => true, 'is_active' => true, 'email_verified_at' => now()]);
        $factory = Factory::create(['name' => $payload, 'slug' => 'xss']);
        $camera = Camera::forceCreate([
            'factory_id' => $factory->id, 'name' => $payload, 'reference' => 'X1', 'status' => 'offline',
            'last_error_message' => $payload,
        ]);
        $event = FireEvent::forceCreate([
            'factory_id' => $factory->id, 'camera_id' => $camera->id, 'event_uuid' => (string) Str::uuid(),
            'type' => 'fire', 'severity' => 'high', 'status' => 'detected', 'confidence' => .9, 'detected_at' => now(),
        ]);
        $alert = Alert::forceCreate([
            'factory_id' => $factory->id, 'fire_event_id' => $event->id, 'type' => 'fire',
            'severity' => 'high', 'status' => 'pending', 'message' => $payload,
        ]);

        foreach ([route('factories.show', $factory), route('alerts.show', $alert), route('camera-health.index')] as $url) {
            $this->actingAs($admin)->withSession(['factory_id' => $factory->id])->get($url)
                ->assertOk()->assertDontSee($payload, false)->assertSee(e($payload), false);
        }
    }

    public function test_profile_rejects_privilege_and_unknown_preference_injection(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name, 'email' => $user->email, 'timezone' => 'UTC',
            'is_super_admin' => true, 'role' => 'super_admin',
            'preferences' => ['theme' => 'dark', 'locale' => 'en', 'permissions' => ['roles.manage']],
        ])->assertSessionHasErrors('preferences');

        $this->assertFalse($user->fresh()->is_super_admin);
        $this->assertCount(0, $user->fresh()->roles);
    }

    public function test_viewer_and_maintenance_agent_cannot_escalate_privileges(): void
    {
        $factory = Factory::create(['name' => 'A', 'slug' => 'a']);
        foreach (['viewer', 'maintenance_agent'] as $roleName) {
            $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
            $factory->users()->attach($user, ['role' => $roleName, 'status' => 'active']);
            $user->roles()->attach(Role::where('name', $roleName)->firstOrFail(), ['factory_id' => $factory->id]);
            $this->actingAs($user)->withSession(['factory_id' => $factory->id])
                ->get(route('roles.index'))->assertForbidden();
            $this->post(route('zones.store'), [
                'name' => 'Forbidden', 'reference' => Str::random(8), 'risk_level' => 'medium', 'status' => 'active',
            ])->assertForbidden();
        }
    }

    public function test_edge_heartbeat_is_throttled_per_api_client(): void
    {
        RateLimiter::clear('unused');
        $factory = Factory::create(['name' => 'A', 'slug' => 'a']);
        $camera = Camera::forceCreate(['factory_id' => $factory->id, 'name' => 'Camera', 'reference' => 'A1', 'status' => 'offline']);
        $credentials = app(ApiClientService::class)->create($factory, ['name' => 'Agent', 'scopes' => ['camera:heartbeat']]);
        $token = $credentials->client->client_id.'.'.$credentials->secret;

        for ($request = 1; $request <= 120; $request++) {
            $this->withToken($token)->postJson(route('edge.cameras.heartbeat', $camera), ['fps' => 10])->assertOk();
        }
        $this->withToken($token)->postJson(route('edge.cameras.heartbeat', $camera), ['fps' => 10])->assertTooManyRequests();
    }

    public function test_edge_rejects_disabled_client_and_stale_timestamp(): void
    {
        $factory = Factory::create(['name' => 'A', 'slug' => 'a']);
        $camera = Camera::forceCreate(['factory_id' => $factory->id, 'name' => 'Camera', 'reference' => 'A1', 'status' => 'offline']);
        $credentials = app(ApiClientService::class)->create($factory, ['name' => 'Agent', 'scopes' => ['camera:heartbeat']]);
        $token = $credentials->client->client_id.'.'.$credentials->secret;

        $this->withToken($token)->postJson(route('edge.cameras.heartbeat', $camera), [
            'sent_at' => now()->subDays(2)->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors('sent_at');

        $credentials->client->update(['status' => 'inactive']);
        $this->withToken($token)->postJson(route('edge.cameras.heartbeat', $camera), [])->assertForbidden();
    }

    public function test_profile_rejects_svg_avatar_upload(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name, 'email' => $user->email, 'timezone' => 'UTC',
            'avatar' => UploadedFile::fake()->create('avatar.svg', 10, 'image/svg+xml'),
        ])->assertSessionHasErrors('avatar');

        $this->assertNull($user->fresh()->avatar_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_malicious_search_input_is_bound_and_does_not_change_query_structure(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true, 'is_active' => true, 'email_verified_at' => now()]);
        Factory::create(['name' => 'Visible', 'slug' => 'visible']);
        Factory::create(['name' => 'Hidden', 'slug' => 'hidden']);

        $this->actingAs($admin)->get(route('factories.index', ['q' => "%' OR 1=1 --"]))
            ->assertOk()->assertSee('Aucune usine.')->assertSee('%&#039; OR 1=1 --', false);
    }

    public function test_factory_settings_require_permission_and_are_audited(): void
    {
        $factory = Factory::create(['name' => 'A', 'slug' => 'a']);
        $viewer = User::factory()->create(['is_active' => true]);
        $viewerRole = Role::where('name', 'viewer')->firstOrFail();
        $factory->users()->attach($viewer, ['role' => 'viewer', 'status' => 'active']);
        $viewer->roles()->attach($viewerRole, ['factory_id' => $factory->id]);
        $viewerToken = app(IssueApiToken::class)->execute($viewer);
        $this->withToken($viewerToken)->withHeader('X-Factory-ID', $factory->id)
            ->patchJson("/api/factories/{$factory->id}/settings", ['retention_days' => 30])
            ->assertForbidden();

        $owner = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $ownerRole = Role::where('name', 'factory_owner')->firstOrFail();
        $factory->users()->attach($owner, ['role' => 'factory_owner', 'status' => 'active']);
        $owner->roles()->attach($ownerRole, ['factory_id' => $factory->id]);
        $this->actingAs($owner)->withSession(['factory_id' => $factory->id])
            ->put(route('settings.update'), [
                'timezone' => 'UTC', 'retention_days' => 90, 'default_fire_threshold' => .5,
                'default_smoke_threshold' => .5, 'alert_cooldown' => 60, 'required_positive_frames' => 3,
            ])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['factory_id' => $factory->id, 'action' => 'settings.updated']);
    }

    public function test_cors_allows_configured_origin_without_credentials_and_rejects_other_origin(): void
    {
        $this->withHeaders([
            'Origin' => 'http://localhost',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/factory-context')
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost')
            ->assertHeaderMissing('Access-Control-Allow-Credentials');

        $response = $this->withHeaders([
            'Origin' => 'https://evil.example',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/factory-context');
        $this->assertNotSame('https://evil.example', $response->headers->get('Access-Control-Allow-Origin'));
    }
}
