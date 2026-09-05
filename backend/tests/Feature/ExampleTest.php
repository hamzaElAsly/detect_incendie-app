<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\Camera;
use App\Models\CameraHealthLog;
use App\Models\Factory;
use App\Models\FireEvent;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_returns_a_successful_response(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_an_authenticated_factory_user_can_render_the_blade_dashboard(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $factory = Factory::create(['name' => 'Local Site', 'slug' => 'local-site']);
        $factory->users()->attach($user, ['role' => 'viewer', 'status' => 'active']);
        $user->roles()->attach(Role::where('name', 'viewer')->firstOrFail(), ['factory_id' => $factory->id]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Command center')
            ->assertSee('Local Site');
    }

    public function test_viewer_is_read_only_and_foreign_records_are_blocked(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $viewer = User::factory()->create(['is_active' => true]);
        $own = Factory::local();
        $own->update(['name' => 'Own', 'slug' => 'own']);
        $other = Factory::create(['name' => 'Other', 'slug' => 'other']);
        $own->users()->attach($viewer, ['role' => 'viewer', 'status' => 'active']);
        $viewer->roles()->attach(Role::where('name', 'viewer')->firstOrFail(), ['factory_id' => $own->id]);
        $camera = Camera::forceCreate(['factory_id' => $other->id, 'name' => 'Foreign', 'reference' => 'F', 'status' => 'online']);
        $event = FireEvent::forceCreate(['factory_id' => $other->id, 'camera_id' => $camera->id, 'event_uuid' => (string) Str::uuid(), 'type' => 'fire', 'severity' => 'high', 'status' => 'detected', 'confidence' => .9, 'detected_at' => now()]);

        $this->actingAs($viewer)->get(route('zones.index'))->assertOk();
        $this->post(route('zones.store'), ['name' => 'X', 'reference' => 'X', 'risk_level' => 'low', 'status' => 'active'])->assertForbidden();
        $this->get(route('events.show', $event))->assertForbidden();
    }

    public function test_last_factory_admin_cannot_be_removed(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $factory = Factory::create(['name' => 'Factory', 'slug' => 'factory']);
        $factory->users()->attach($admin, ['role' => 'factory_admin', 'status' => 'active']);
        $admin->roles()->attach(Role::where('name', 'factory_admin')->firstOrFail(), ['factory_id' => $factory->id]);

        $this->actingAs($admin)->delete(route('members.destroy', $admin))->assertStatus(422);
        $this->assertDatabaseHas('factory_user', ['factory_id' => $factory->id, 'user_id' => $admin->id]);
    }

    public function test_api_secret_is_visible_on_only_one_response(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $factory = Factory::create(['name' => 'API Factory', 'slug' => 'api-factory']);
        $admin->roles()->attach(Role::where('name', 'system_admin')->firstOrFail(), ['factory_id' => null]);

        $response = $this->actingAs($admin)->post(route('api-clients.store'), ['name' => 'Detector', 'allowed_ips_text' => '127.0.0.1']);
        $client = ApiClient::firstOrFail();
        $response->assertRedirect(route('api-clients.show', $client));
        $this->get(route('api-clients.show', $client))->assertSee('Secret visible une seule fois');
        $this->get(route('api-clients.show', $client))->assertDontSee('Secret visible une seule fois');
        $this->assertNotEmpty($client->getRawOriginal('secret_hash'));
    }

    public function test_demo_seeder_creates_on_premise_roles_and_operational_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('roles', 7);
        $this->assertSame(1, Factory::count());
        $this->assertGreaterThanOrEqual(7, User::count());
        $this->assertDatabaseHas('users', ['email' => 'sysadmin@firewatch.local']);
        $this->assertGreaterThan(0, CameraHealthLog::count());
        $this->assertDatabaseMissing('permissions', ['name' => 'plans.view']);
        $this->assertDatabaseMissing('permissions', ['name' => 'subscriptions.view']);
    }

    public function test_navigation_and_access_are_adapted_to_on_premise_roles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $factory = Factory::create(['name' => 'Roles Factory', 'slug' => 'roles-factory']);

        foreach (['system_admin', 'factory_admin', 'factory_manager', 'safety_officer', 'security_agent', 'maintenance_agent', 'viewer'] as $roleName) {
            $user = User::factory()->create(['email' => $roleName.'@roles.test', 'is_active' => true, 'is_super_admin' => $roleName === 'system_admin']);
            $role = Role::where('name', $roleName)->firstOrFail();
            $user->roles()->attach($role, ['factory_id' => $role->is_platform ? null : $factory->id]);
            if (! $role->is_platform) {
                $factory->users()->attach($user, ['role' => $roleName, 'status' => 'active']);
            }

            $response = $this->actingAs($user)->get('/dashboard')->assertOk();
            $response->assertDontSee('Plans SaaS');
            $response->assertDontSee('Subscriptions');
            if ($roleName === 'maintenance_agent') {
                $this->get(route('camera-health.index'))->assertOk();
                $this->get(route('alerts.index'))->assertForbidden();
            }
            if ($roleName === 'safety_officer') {
                $this->get(route('events.index'))->assertOk();
                $this->get(route('cameras.create'))->assertForbidden();
            }
            auth()->logout();
        }
    }

    public function test_system_admin_can_manage_system_modules_and_profile(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
        Factory::create(['name' => 'Local', 'slug' => 'local']);

        $this->actingAs($admin)->get(route('factory-settings.edit'))->assertOk();
        $this->get(route('users.index'))->assertOk();
        $this->get(route('roles.index'))->assertOk();
        $this->get(route('settings.edit'))->assertOk();
        $this->get(route('profile.edit'))->assertOk()->assertSee($admin->email);
    }
}
