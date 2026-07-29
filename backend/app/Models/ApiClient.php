<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ApiClient extends Model
{
    use BelongsToFactory,HasUlids;

    public const SCOPES = ['camera:heartbeat', 'camera:read'];

    protected $fillable = ['name', 'client_id', 'status', 'last_used_at', 'last_used_ip', 'allowed_ips', 'scopes', 'expires_at', 'revoked_at'];

    protected $hidden = ['secret_hash'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime', 'allowed_ips' => 'array', 'scopes' => 'array', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }

    public function statusLabel(): string
    {
        if ($this->status === 'revoked' || $this->revoked_at) {
            return 'revoked';
        }
        if ($this->status !== 'active') {
            return 'disabled';
        }
        if ($this->expires_at?->isPast()) {
            return 'expired';
        }

        return 'active';
    }
}
