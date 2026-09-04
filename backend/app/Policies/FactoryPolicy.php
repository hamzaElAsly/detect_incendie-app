<?php

namespace App\Policies;

use App\Models\Factory;
use App\Models\User;

class FactoryPolicy
{
    public function view(User $user, Factory $factory): bool
    {
        return (bool) ($user->is_super_admin ?? false) || $user->belongsToFactory($factory);
    }

    public function update(User $user, Factory $factory): bool
    {
        return (bool) ($user->is_super_admin ?? false) || $factory->users()->whereKey($user->id)->wherePivotIn('role', ['factory_admin'])->exists();
    }
}
