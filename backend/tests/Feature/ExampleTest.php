<?php

namespace Tests\Feature;

use App\Models\Factory;
use App\Models\Camera;
use App\Models\FireEvent;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_an_authenticated_factory_user_can_render_the_blade_dashboard(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $factory = Factory::create(['name' => 'Usine Blade', 'slug' => 'usine-blade']);
        $factory->users()->attach($user, ['role' => 'viewer', 'status' => 'active']);
        $user->roles()->attach(Role::where('name', 'viewer')->firstOrFail(), ['factory_id' => $factory->id]);

        $this->actingAs($user)
            ->withSession(['factory_id' => $factory->id])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Centre de surveillance')
            ->assertSee('Usine Blade');
    }

    public function test_a_user_cannot_select_another_factory(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $other = Factory::create(['name' => 'Autre usine', 'slug' => 'autre']);

        $this->actingAs($user)->post(route('factory.switch', $other))->assertForbidden();
    }

    public function test_viewer_is_read_only_and_cross_tenant_records_are_blocked(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $viewer=User::factory()->create(['is_active'=>true]);$own=Factory::create(['name'=>'Own','slug'=>'own']);$other=Factory::create(['name'=>'Other','slug'=>'other']);
        $own->users()->attach($viewer,['role'=>'viewer','status'=>'active']);$viewer->roles()->attach(Role::where('name','viewer')->firstOrFail(),['factory_id'=>$own->id]);
        $camera=Camera::forceCreate(['factory_id'=>$other->id,'name'=>'Foreign','reference'=>'F','status'=>'online']);$event=FireEvent::forceCreate(['factory_id'=>$other->id,'camera_id'=>$camera->id,'event_uuid'=>(string)Str::uuid(),'type'=>'fire','severity'=>'high','status'=>'detected','confidence'=>.9,'detected_at'=>now()]);
        $this->actingAs($viewer)->withSession(['factory_id'=>$own->id])->get(route('zones.index'))->assertOk();
        $this->post(route('zones.store'),['name'=>'X','reference'=>'X','risk_level'=>'low','status'=>'active'])->assertForbidden();
        $this->get(route('events.show',$event))->assertForbidden();
    }

    public function test_last_factory_owner_cannot_be_removed(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);$owner=User::factory()->create(['is_active'=>true]);$factory=Factory::create(['name'=>'Factory','slug'=>'factory']);$factory->users()->attach($owner,['role'=>'factory_owner','status'=>'active']);$owner->roles()->attach(Role::where('name','factory_owner')->firstOrFail(),['factory_id'=>$factory->id]);
        $this->actingAs($owner)->withSession(['factory_id'=>$factory->id])->delete(route('members.destroy',$owner))->assertStatus(422);
        $this->assertDatabaseHas('factory_user',['factory_id'=>$factory->id,'user_id'=>$owner->id]);
    }

    public function test_api_secret_is_visible_on_only_one_response(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);$admin=User::factory()->create(['is_active'=>true]);$factory=Factory::create(['name'=>'API Factory','slug'=>'api-factory']);$admin->roles()->attach(Role::where('name','platform_admin')->firstOrFail(),['factory_id'=>null]);
        $response=$this->actingAs($admin)->withSession(['factory_id'=>$factory->id])->post(route('api-clients.store'),['name'=>'Detector','allowed_ips_text'=>'127.0.0.1']);$client=\App\Models\ApiClient::firstOrFail();
        $response->assertRedirect(route('api-clients.show',$client));$this->get(route('api-clients.show',$client))->assertSee('Secret visible une seule fois');$this->get(route('api-clients.show',$client))->assertDontSee('Secret visible une seule fois');$this->assertNotEmpty($client->getRawOriginal('secret_hash'));
    }

    public function test_demo_seeder_creates_all_roles_and_operational_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('roles',8);$this->assertGreaterThanOrEqual(2,Factory::count());$this->assertGreaterThanOrEqual(8,User::count());$this->assertDatabaseHas('users',['email'=>'superadmin@firewatch.demo']);$this->assertGreaterThan(0,\App\Models\CameraHealthLog::count());$this->assertGreaterThan(0,\App\Models\Subscription::count());
    }

    public function test_navigation_and_access_are_adapted_to_every_role(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);$factory=Factory::create(['name'=>'Roles Factory','slug'=>'roles-factory']);
        foreach(['super_admin','platform_admin','factory_owner','factory_manager','safety_officer','security_agent','maintenance_agent','viewer'] as $roleName){$user=User::factory()->create(['email'=>$roleName.'@roles.test','is_active'=>true,'is_super_admin'=>$roleName==='super_admin']);if($roleName!=='super_admin'){$role=Role::where('name',$roleName)->firstOrFail();$user->roles()->attach($role,['factory_id'=>$role->is_platform?null:$factory->id]);}if(!in_array($roleName,['super_admin','platform_admin'],true))$factory->users()->attach($user,['role'=>$roleName,'status'=>'active']);$response=$this->actingAs($user)->withSession(['factory_id'=>$factory->id])->get('/dashboard')->assertOk();if(in_array($roleName,['super_admin','platform_admin'],true))$response->assertSee('Plans');else $response->assertDontSee('Plans SaaS');if($roleName==='maintenance_agent'){$response->assertSee('Santé caméras');$this->get(route('camera-health.index'))->assertOk();$this->get(route('alerts.index'))->assertForbidden();}if($roleName==='safety_officer'){$this->get(route('events.index'))->assertOk();$this->get(route('cameras.create'))->assertForbidden();}auth()->logout();}
    }

    public function test_super_admin_can_manage_platform_modules_and_profile(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);$admin=User::factory()->create(['is_active'=>true,'is_super_admin'=>true]);Factory::create(['name'=>'Platform','slug'=>'platform']);
        $this->actingAs($admin)->get(route('factories.index'))->assertOk();$this->get(route('users.index'))->assertOk();$this->get(route('roles.index'))->assertOk();$this->get(route('plans.index'))->assertOk();$this->get(route('subscriptions.index'))->assertOk();$this->get(route('profile.edit'))->assertOk()->assertSee($admin->email);
    }
}
