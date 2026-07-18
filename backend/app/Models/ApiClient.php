<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ApiClient extends Model
{
    use BelongsToFactory,HasUlids;

    protected $fillable = ['name', 'client_id', 'status', 'last_used_at', 'allowed_ips'];

    protected $hidden = ['secret_hash'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime', 'allowed_ips' => 'array'];
    }
}
