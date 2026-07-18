<?php

namespace App\Authorization;

use App\Models\ApiClient;
use App\Models\Factory;
use App\Models\User;

class ApiClientPolicy
{
    public function viewAny(User $user, ?Factory $factory = null): bool
    {
        return $user->hasPermission('api_clients.view', $factory);
    }

    public function view(User $user, ApiClient $record): bool
    {
        return $user->canAccessFactory($record->factory) && $user->hasPermission('api_clients.view', $record->factory);
    }

    public function create(User $user, Factory $factory): bool
    {
        return $user->canAccessFactory($factory) && $user->hasPermission('api_clients.manage', $factory);
    }

    public function update(User $user, ApiClient $record): bool
    {
        return $user->canAccessFactory($record->factory) && $user->hasPermission('api_clients.manage', $record->factory);
    }

    public function delete(User $user, ApiClient $record): bool
    {
        return false;
    }
}
