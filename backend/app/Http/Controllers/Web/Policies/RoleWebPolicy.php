<?php

namespace App\Http\Controllers\Web\Policies;

use App\Models\Role;
use App\Models\User;

class RoleWebPolicy
{
    public function before(User $u): ?bool
    {
        return $u->is_super_admin ? true : null;
    }

    public function viewAny(User $u): bool
    {
        return $u->hasPermission('roles.view');
    }

    public function view(User $u, Role $r): bool
    {
        return $this->viewAny($u);
    }

    public function update(User $u, Role $r): bool
    {
        return $u->hasPermission('roles.manage') && ! in_array($r->name, ['super_admin', 'platform_admin'], true);
    }
}
