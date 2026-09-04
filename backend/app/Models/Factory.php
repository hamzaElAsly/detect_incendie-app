<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Factory extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'name',
        'legal_name',
        'slug',
        'reference',
        'installation_identifier',
        'address',
        'city',
        'country',
        'phone',
        'email',
        'emergency_contact',
        'timezone',
        'status',
        'owner_id',
        'is_active',
        'logo_path',
        'latitude',
        'longitude',
        'internal_notes',
        'settings',
    ];

    protected function casts(): array
    {
        return ['status' => RecordStatus::class, 'is_active' => 'boolean', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'settings' => 'array'];
    }

    public static function local(): self
    {
        return static::query()
            ->where('is_active', true)
            ->orderBy('created_at')
            ->orderBy('name')
            ->first()
            ?? static::query()->orderBy('created_at')->orderBy('name')->first()
            ?? static::query()->create([
                'name' => config('app.name', 'Local Fire Detection Site'),
                'slug' => 'local-installation',
                'reference' => 'LOCAL-INSTALLATION',
                'timezone' => config('app.timezone', 'UTC'),
                'status' => 'active',
                'is_active' => true,
            ]);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'status', 'invited_at', 'joined_at'])
            ->withTimestamps();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }

    public function cameras(): HasMany
    {
        return $this->hasMany(Camera::class);
    }

    public function fireEvents(): HasMany
    {
        return $this->hasMany(FireEvent::class);
    }

    public function settingsEntries(): HasMany
    {
        return $this->hasMany(FactorySetting::class);
    }

    public function apiClients(): HasMany
    {
        return $this->hasMany(ApiClient::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function alertRecipients(): HasMany
    {
        return $this->hasMany(AlertRecipient::class);
    }
}
