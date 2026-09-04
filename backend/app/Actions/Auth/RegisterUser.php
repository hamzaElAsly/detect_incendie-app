<?php

namespace App\Actions\Auth;

use App\Models\Factory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterUser
{
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'is_active' => true, 'status' => 'active']);
            $factory = Factory::local();
            $role = Role::firstOrCreate(['name' => 'factory_admin'], ['label' => 'Factory Admin', 'is_platform' => false]);
            $factory->users()->syncWithoutDetaching([
                $user->id => ['role' => $role->name, 'status' => 'active', 'joined_at' => now()],
            ]);
            $user->roles()->syncWithoutDetaching([$role->id => ['factory_id' => $factory->id]]);

            return [$user, $factory];
        });
    }
}
