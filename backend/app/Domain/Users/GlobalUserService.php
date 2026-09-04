<?php

namespace App\Domain\Users;

use App\Domain\Audit\AuditService;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GlobalUserService
{
    public function __construct(private AuditService $audit) {}

    public function create(array $data, Role $role, User $actor): User
    {
        return DB::transaction(function () use ($data, $role, $actor) {
            $active = (bool) ($data['is_active'] ?? false);
            $user = User::create(['name' => $data['name'], 'email' => mb_strtolower(trim($data['email'])), 'phone' => $data['phone'] ?? null, 'password' => $data['password'], 'is_active' => $active, 'status' => $active ? 'active' : 'inactive', 'is_super_admin' => $role->name === 'system_admin']);
            $user->roles()->attach($role, ['factory_id' => null]);
            $this->audit->record('user.created', $user, [], ['name' => $user->name, 'email' => $user->email, 'role' => $role->name, 'is_active' => $active], null, $actor);

            return $user;
        });
    }
}
