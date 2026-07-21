<?php

namespace Tests\Feature;

use App\Domain\Members\MembershipService;
use App\Domain\Subscriptions\SubscriptionService;
use App\Models\AlertRecipient;
use App\Models\ApiClient;
use App\Models\Camera;
use App\Models\Factory;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase2ModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_existing_user_can_be_invited_without_creating_a_duplicate(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $existing = User::factory()->create(['email' => 'existing@example.test']);
        $factory = Factory::create(['name' => 'Alpha', 'slug' => 'alpha']);

        app(MembershipService::class)->invite($factory, ['name' => 'Existing', 'email' => ' Existing@Example.Test ', 'role' => 'viewer'], $admin);

        $this->assertSame(1, User::where('email', 'existing@example.test')->count());
        $this->assertTrue($existing->fresh()->belongsToFactory($factory));
        $this->assertDatabaseHas('audit_logs', ['action' => 'member.invited', 'factory_id' => $factory->id]);
    }

    public function test_factory_owner_cannot_escalate_a_member_to_owner(): void
    {
        [$owner, $factory] = $this->factoryUser('factory_owner');
        $this->expectException(ValidationException::class);
        app(MembershipService::class)->invite($factory, ['name' => 'Second Owner', 'email' => 'second@example.test', 'role' => 'factory_owner'], $owner);
    }

    public function test_cross_factory_resources_are_forbidden_by_direct_url(): void
    {
        [$manager, $own] = $this->factoryUser('factory_owner');
        $other = Factory::create(['name' => 'Other', 'slug' => 'other']);
        $camera = Camera::forceCreate(['factory_id' => $other->id, 'name' => 'Foreign', 'reference' => 'FOREIGN', 'status' => 'offline']);
        $recipient = AlertRecipient::forceCreate(['factory_id' => $other->id, 'name' => 'Foreign', 'email' => 'foreign@example.test', 'channels' => ['email'], 'severity_min' => 'low']);
        $client = ApiClient::forceCreate(['factory_id' => $other->id, 'name' => 'Foreign', 'client_id' => 'foreign-client', 'secret_hash' => hash('sha256', 'secret'), 'status' => 'active']);

        $this->actingAs($manager)->withSession(['factory_id' => $own->id]);
        $this->get(route('camera-health.show', $camera))->assertForbidden();
        $this->get(route('recipients.edit', $recipient))->assertForbidden();
        $this->get(route('api-clients.show', $client))->assertForbidden();
    }

    public function test_only_one_current_subscription_is_allowed_per_factory(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $factory = Factory::create(['name' => 'Billing', 'slug' => 'billing']);
        $plan = Plan::create(['name' => 'One', 'code' => 'one', 'is_active' => true]);
        app(SubscriptionService::class)->create(['factory_id' => $factory->id, 'plan_id' => $plan->id, 'status' => 'active'], $admin);

        $this->expectException(ValidationException::class);
        app(SubscriptionService::class)->create(['factory_id' => $factory->id, 'plan_id' => $plan->id, 'status' => 'trial'], $admin);
    }

    public function test_plan_member_limit_is_enforced(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $factory = Factory::create(['name' => 'Limited', 'slug' => 'limited']);
        $factory->users()->attach($admin, ['role' => 'factory_owner', 'status' => 'active']);
        $plan = Plan::create(['name' => 'Starter', 'code' => 'starter', 'max_users' => 1, 'is_active' => true]);
        Subscription::forceCreate(['factory_id' => $factory->id, 'plan_id' => $plan->id, 'status' => 'active']);

        $this->expectException(ValidationException::class);
        app(MembershipService::class)->invite($factory, ['name' => 'Blocked', 'email' => 'blocked@example.test', 'role' => 'viewer'], $admin);
    }

    public function test_system_roles_cannot_be_modified_even_by_super_admin(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        Factory::create(['name' => 'Platform', 'slug' => 'platform']);
        $role = Role::where('name', 'super_admin')->firstOrFail();

        $this->actingAs($admin)->put(route('roles.update', $role), ['permissions' => []])->assertForbidden();
    }

    public function test_profile_password_requires_current_password_and_never_changes_role(): void
    {
        [$user, $factory] = $this->factoryUser('viewer');
        $this->actingAs($user)->withSession(['factory_id' => $factory->id])
            ->put(route('profile.password'), ['current_password' => 'wrong', 'password' => 'New-password-123!', 'password_confirmation' => 'New-password-123!'])
            ->assertSessionHasErrors('current_password');
        $this->assertFalse(Hash::check('New-password-123!', $user->fresh()->password));
        $this->assertTrue($user->fresh()->hasRole('viewer', $factory));
    }

    private function factoryUser(string $roleName): array
    {
        $user = User::factory()->create(['is_active' => true]);
        $factory = Factory::create(['name' => ucfirst($roleName), 'slug' => $roleName.'-'.str()->random(6)]);
        $role = Role::where('name', $roleName)->firstOrFail();
        $factory->users()->attach($user, ['role' => $roleName, 'status' => 'active']);
        $user->roles()->attach($role, ['factory_id' => $factory->id]);

        return [$user, $factory];
    }
}
