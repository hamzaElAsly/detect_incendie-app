<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany as RoleBelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'is_super_admin',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function factories(): BelongsToMany
    {
        return $this->belongsToMany(Factory::class)->withPivot('role')->withTimestamps();
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function roles(): RoleBelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withPivot('factory_id');
    }

    public function hasRole(string $role, ?Factory $factory = null): bool
    {
        if ($this->is_super_admin && $role === 'super_admin') {
            return true;
        }

        return $this->roles()
            ->where('roles.name', $role)
            ->where(function ($query) use ($factory): void {
                $query->where('roles.is_platform', true);
                if ($factory) {
                    $query->orWhere('role_user.factory_id', $factory->getKey());
                }
            })->exists();
    }

    public function hasPermission(string $permission, ?Factory $factory = null): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('permissions.name', $permission))
            ->where(function ($query) use ($factory): void {
                $query->where('roles.is_platform', true);
                if ($factory) {
                    $query->orWhere('role_user.factory_id', $factory->getKey());
                }
            })->exists();
    }

    public function canAccessFactory(Factory $factory): bool
    {
        return $this->is_super_admin
            || $this->roles()->where('roles.is_platform', true)->exists()
            || $this->belongsToFactory($factory);
    }

    public function belongsToFactory(Factory $factory): bool
    {
        return $this->factories()->whereKey($factory->getKey())->exists();
    }
}
