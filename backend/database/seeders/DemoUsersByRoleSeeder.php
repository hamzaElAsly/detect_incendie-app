<?php

namespace Database\Seeders;

use App\Models\Factory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoUsersByRoleSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $factory = Factory::local();
            $password = Hash::make(env('DEMO_USERS_PASSWORD', 'password'));

            $users = [
                ['role' => 'system_admin', 'name' => 'System Admin', 'email' => 'sysadmin@firefactory.local', 'platform' => true],
                ['role' => 'factory_admin', 'name' => 'Factory Admin', 'email' => 'admin@firefactory.local'],
                ['role' => 'factory_manager', 'name' => 'Factory Manager', 'email' => 'manager@firefactory.local'],
                ['role' => 'safety_officer', 'name' => 'Safety Officer', 'email' => 'safety@firefactory.local'],
                ['role' => 'security_agent', 'name' => 'Security Agent', 'email' => 'security@firefactory.local'],
                ['role' => 'maintenance_agent', 'name' => 'Maintenance Agent', 'email' => 'maintenance@firefactory.local'],
                ['role' => 'viewer', 'name' => 'Viewer', 'email' => 'viewer@firefactory.local'],
            ];

            foreach ($users as $data) {
                $user = User::updateOrCreate(
                    ['email' => $data['email']],
                    ['name' => $data['name'], 'phone' => null, 'password' => $password, 'status' => 'active', 'is_active' => true, 'is_super_admin' => $data['role'] === 'system_admin', 'email_verified_at' => now()],
                );
                $role = Role::where('name', $data['role'])->firstOrFail();
                $user->roles()->syncWithoutDetaching([$role->id => ['factory_id' => $role->is_platform ? null : $factory->id]]);

                if (! ($data['platform'] ?? false)) {
                    $factory->users()->syncWithoutDetaching([
                        $user->id => ['role' => $data['role'], 'status' => 'active', 'joined_at' => now()],
                    ]);
                }

                if ($data['role'] === 'factory_admin') {
                    $factory->update(['owner_id' => $user->id]);
                }
            }
        });
    }
}
