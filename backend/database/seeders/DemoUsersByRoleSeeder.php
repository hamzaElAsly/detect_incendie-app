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
        DB::transaction(function () {
            $factory = Factory::firstOrCreate(
                ['reference' => 'FACTORY-DEMO-001'],
                [
                    'name' => 'Factory Demo',
                    'slug' => 'factory-demo',
                    'city' => 'Fès',
                    'country' => 'Morocco',
                    'timezone' => 'Africa/Fès',
                    'status' => 'active',
                ]
            );

            $password = Hash::make(env('DEMO_USERS_PASSWORD', 'password'));

            $users = [
                [
                    'role' => 'super_admin',
                    'name' => 'Super Admin',
                    'email' => 'superadmin@firefactory.test',
                    'platform' => true,
                ],
                [
                    'role' => 'platform_admin',
                    'name' => 'Platform Admin',
                    'email' => 'platformadmin@firefactory.test',
                    'platform' => true,
                ],
                [
                    'role' => 'factory_owner',
                    'name' => 'Factory Owner',
                    'email' => 'owner@firefactory.test',
                ],
                [
                    'role' => 'factory_manager',
                    'name' => 'Factory Manager',
                    'email' => 'manager@firefactory.test',
                ],
                [
                    'role' => 'safety_officer',
                    'name' => 'Safety Officer',
                    'email' => 'safety@firefactory.test',
                ],
                [
                    'role' => 'security_agent',
                    'name' => 'Security Agent',
                    'email' => 'security@firefactory.test',
                ],
                [
                    'role' => 'maintenance_agent',
                    'name' => 'Maintenance Agent',
                    'email' => 'maintenance@firefactory.test',
                ],
                [
                    'role' => 'viewer',
                    'name' => 'Viewer',
                    'email' => 'viewer@firefactory.test',
                ],
            ];

            foreach ($users as $data) {
                $user = User::updateOrCreate(
                    ['email' => $data['email']],
                    [
                        'name' => $data['name'],
                        'phone' => null,
                        'password' => $password,
                        'status' => 'active',
                        'email_verified_at' => now(),
                    ]
                );

                $role = Role::where('name', $data['role'])->firstOrFail();

                if (! ($data['platform'] ?? false)) {
                    $factory->users()->syncWithoutDetaching([
                        $user->id => [
                            'status' => 'active',
                            'joined_at' => now(),
                        ],
                    ]);
                }

                DB::table('role_user')->updateOrInsert(
                    [
                        'role_id' => $role->id,
                        'user_id' => $user->id,
                        'factory_id' => ($data['platform'] ?? false)
                            ? null
                            : $factory->id,
                    ],
                    []
                );

                if ($data['role'] === 'factory_owner') {
                    $factory->update([
                        'owner_id' => $user->id,
                    ]);
                }
            }
        });
    }
}
