<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Factory extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = ['name', 'slug', 'reference', 'address', 'city', 'country', 'phone', 'email', 'timezone', 'status', 'owner_id', 'is_active', 'settings'];

    protected function casts(): array
    {
        return ['status' => RecordStatus::class, 'is_active' => 'boolean', 'settings' => 'array'];
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

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function currentSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->whereIn('status', ['trial', 'active'])
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->latestOfMany();
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
