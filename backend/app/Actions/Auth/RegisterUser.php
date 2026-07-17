<?php

namespace App\Actions\Auth;

use App\Models\Factory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterUser
{
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            $base = Str::slug($data['factory_name']);
            $slug = $base;
            for ($i = 2; Factory::where('slug', $slug)->exists(); $i++) {
                $slug = $base.'-'.$i;
            }
            $factory = Factory::create(['name' => $data['factory_name'], 'slug' => $slug]);
            $factory->users()->attach($user, ['role' => 'factory_owner']);

            return [$user, $factory];
        });
    }
}
