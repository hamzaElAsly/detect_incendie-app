<?php

namespace App\Authorization;

use App\Models\AlertRecipient;
use App\Models\Factory;
use App\Models\User;

class AlertRecipientPolicy
{
    public function viewAny(User $user, ?Factory $factory = null): bool
    {
        return $user->hasPermission('recipients.view', $factory);
    }

    public function view(User $user, AlertRecipient $record): bool
    {
        return $user->canAccessFactory($record->factory) && $user->hasPermission('recipients.view', $record->factory);
    }

    public function create(User $user, Factory $factory): bool
    {
        return $user->canAccessFactory($factory) && $user->hasPermission('recipients.manage', $factory);
    }

    public function update(User $user, AlertRecipient $record): bool
    {
        return $user->canAccessFactory($record->factory) && $user->hasPermission('recipients.manage', $record->factory);
    }

    public function delete(User $user, AlertRecipient $record): bool
    {
        return $this->update($user, $record);
    }
}
