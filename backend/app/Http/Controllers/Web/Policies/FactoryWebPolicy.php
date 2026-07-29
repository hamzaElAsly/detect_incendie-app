<?php

namespace App\Http\Controllers\Web\Policies;

use App\Models\Factory;
use App\Models\User;

class FactoryWebPolicy
{
    public function before(User $u): ?bool
    {
        return $u->is_super_admin ? true : null;
    }

    public function viewAny(User $u): bool
    {
        return $u->roles()->where('roles.is_platform', true)->exists();
    }

    public function view(User $u, Factory $f): bool
    {
        return $u->canAccessFactory($f) && $u->hasPermission('factories.view', $f);
    }

    public function create(User $u): bool
    {
        return $u->hasPermission('factories.create');
    }

    public function update(User $u, Factory $f): bool
    {
        return $u->canAccessFactory($f) && $u->hasPermission('factories.update', $f);
    }

    public function delete(User $u, Factory $f): bool
    {
        return $u->hasPermission('factories.delete') && ! $f->is_active && ! $f->fireEvents()->exists();
    }
}
