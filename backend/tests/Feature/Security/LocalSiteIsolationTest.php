<?php

namespace Tests\Feature\Security;

use App\Models\AlertRecipient;
use App\Models\ApiClient;
use App\Models\Camera;
use App\Models\Factory;
use App\Models\Role;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalSiteIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Factory $factoryA;

    private Factory $factoryB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->actor = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $this->actor->roles()->attach(Role::where('name', 'system_admin')->firstOrFail(), ['factory_id' => null]);
        $this->factoryA = Factory::create(['name' => 'Factory A', 'slug' => 'factory-a']);
        $this->factoryB = Factory::create(['name' => 'Factory B', 'slug' => 'factory-b']);
        $this->actingAs($this->actor)->withSession(['factory_id' => $this->factoryA->id]);
    }

    public function test_local_site_indexes_do_not_disclose_other_factory_records(): void
    {
        Zone::forceCreate(['factory_id' => $this->factoryA->id, 'name' => 'Visible A', 'reference' => 'A']);
        Zone::forceCreate(['factory_id' => $this->factoryB->id, 'name' => 'Secret B', 'reference' => 'B']);
        Camera::forceCreate(['factory_id' => $this->factoryB->id, 'name' => 'Secret Camera B', 'reference' => 'B']);
        AlertRecipient::forceCreate(['factory_id' => $this->factoryB->id, 'name' => 'Secret Recipient B', 'channels' => ['email'], 'email' => 'b@example.test']);

        $this->get(route('zones.index'))->assertOk()->assertSee('Visible A')->assertDontSee('Secret B');
        $this->get(route('cameras.index'))->assertOk()->assertDontSee('Secret Camera B');
        $this->get(route('recipients.index'))->assertOk()->assertDontSee('Secret Recipient B');
    }

    public function test_foreign_site_route_binding_cannot_read_edit_update_or_delete_resources(): void
    {
        $zone = Zone::forceCreate(['factory_id' => $this->factoryB->id, 'name' => 'B', 'reference' => 'B']);
        $camera = Camera::forceCreate(['factory_id' => $this->factoryB->id, 'name' => 'B', 'reference' => 'B']);
        $recipient = AlertRecipient::forceCreate(['factory_id' => $this->factoryB->id, 'name' => 'B', 'channels' => ['email'], 'email' => 'b@example.test']);
        $client = ApiClient::forceCreate(['factory_id' => $this->factoryB->id, 'name' => 'B', 'client_id' => 'b', 'secret_hash' => hash('sha256', 'x'), 'status' => 'active']);

        foreach ([
            ['get', route('zones.edit', $zone), []], ['put', route('zones.update', $zone), []], ['delete', route('zones.destroy', $zone), []],
            ['get', route('cameras.edit', $camera), []], ['put', route('cameras.update', $camera), []], ['delete', route('cameras.destroy', $camera), []],
            ['get', route('recipients.edit', $recipient), []], ['put', route('recipients.update', $recipient), []], ['delete', route('recipients.destroy', $recipient), []],
            ['get', route('api-clients.show', $client), []],
        ] as [$method, $url, $data]) {
            $this->{$method}($url, $data)->assertForbidden();
        }
    }

    public function test_forged_factory_and_nested_zone_ids_cannot_move_resources_between_sites(): void
    {
        $foreignZone = Zone::forceCreate(['factory_id' => $this->factoryB->id, 'name' => 'Foreign', 'reference' => 'F']);
        $this->post(route('zones.store'), [
            'factory_id' => $this->factoryB->id, 'name' => 'Created A', 'reference' => 'A1',
            'risk_level' => 'medium', 'status' => 'active',
        ])->assertRedirect();
        $this->assertDatabaseHas('zones', ['factory_id' => $this->factoryA->id, 'reference' => 'A1']);
        $this->assertDatabaseMissing('zones', ['factory_id' => $this->factoryB->id, 'reference' => 'A1']);

        $this->post(route('cameras.store'), [
            'factory_id' => $this->factoryB->id, 'zone_id' => $foreignZone->id,
            'name' => 'Forged', 'reference' => 'C1', 'stream_type' => 'rtsp',
            'status' => 'offline', 'fire_threshold' => .5, 'smoke_threshold' => .5,
        ])->assertSessionHasErrors('zone_id');
        $this->assertDatabaseMissing('cameras', ['reference' => 'C1']);
    }
}
