<?php

namespace App\Domain\Members;

use App\Domain\Audit\AuditService;
use App\Models\Factory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MembershipService
{
    public function __construct(private readonly AuditService $audit) {}

    public function invite(Factory $factory, array $data, User $actor): User
    {
        $this->ensureAssignableRole($actor, $data['role']);
        $limit = $factory->currentSubscription()->with('plan')->first()?->plan?->max_users;
        if ($limit !== null && $factory->users()->count() >= $limit) {
            throw ValidationException::withMessages(['email' => __('phase2.members.plan_limit')]);
        }
        $email = Str::lower(trim($data['email']));
        $existing = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($existing?->belongsToFactory($factory)) {
            throw ValidationException::withMessages(['email' => __('phase2.members.already_member')]);
        }

        return DB::transaction(function () use ($factory, $data, $actor, $email, $existing): User {
            $user = $existing ?? User::create([
                'name' => $data['name'], 'email' => $email, 'phone' => $data['phone'] ?? null,
                'password' => Hash::make(Str::random(48)), 'is_active' => true,
            ]);
            $role = Role::query()->where('name', $data['role'])->where('is_platform', false)->firstOrFail();
            $factory->users()->attach($user, ['role' => $role->name, 'status' => 'invited', 'invited_at' => now()]);
            $user->roles()->syncWithoutDetaching([$role->id => ['factory_id' => $factory->id]]);
            $this->audit->record('member.invited', $user, [], ['role' => $role->name], $factory, $actor);

            return $user;
        });
    }

    public function update(Factory $factory, User $member, array $data, User $actor, bool $active): void
    {
        $currentRole = $member->factories()->whereKey($factory->id)->firstOrFail()->pivot->role;
        $this->ensureAssignableRole($actor, $data['role']);
        $this->protectOwner($factory, $member, $currentRole, $data['role'], $active);

        DB::transaction(function () use ($factory, $member, $data, $actor, $active, $currentRole): void {
            $member->update(['name' => $data['name'] ?? $member->name, 'email' => Str::lower(trim($data['email'] ?? $member->email)), 'phone' => $data['phone'] ?? $member->phone, 'is_active' => $active]);
            $factory->users()->updateExistingPivot($member->id, ['role' => $data['role'], 'status' => $active ? 'active' : 'inactive']);
            $member->roles()->wherePivot('factory_id', $factory->id)->detach();
            $role = Role::query()->where('name', $data['role'])->where('is_platform', false)->firstOrFail();
            $member->roles()->attach($role, ['factory_id' => $factory->id]);
            $this->audit->record('member.updated', $member, ['role' => $currentRole], ['role' => $role->name, 'active' => $active], $factory, $actor);
        });
    }

    public function remove(Factory $factory, User $member, User $actor): void
    {
        $role = $member->factories()->whereKey($factory->id)->firstOrFail()->pivot->role;
        $this->protectOwner($factory, $member, $role, 'removed', false);
        DB::transaction(function () use ($factory, $member, $actor, $role): void {
            $factory->users()->detach($member);
            $member->roles()->wherePivot('factory_id', $factory->id)->detach();
            $this->audit->record('member.removed', $member, ['role' => $role], [], $factory, $actor);
        });
    }

    private function ensureAssignableRole(User $actor, string $role): void
    {
        $isPlatformAdministrator = $actor->is_super_admin || $actor->roles()->where('roles.is_platform', true)->exists();
        if ($role === 'factory_owner' && ! $isPlatformAdministrator) {
            throw ValidationException::withMessages(['role' => __('phase2.members.owner_assignment_forbidden')]);
        }
    }

    private function protectOwner(Factory $factory, User $member, string $currentRole, string $nextRole, bool $active): void
    {
        if ($currentRole !== 'factory_owner' || ($nextRole === 'factory_owner' && $active)) {
            return;
        }
        $anotherOwnerExists = $factory->users()->wherePivot('role', 'factory_owner')->wherePivot('status', 'active')->whereKeyNot($member->id)->exists();
        abort_unless($anotherOwnerExists, 422, __('phase2.members.last_owner'));
    }
}
