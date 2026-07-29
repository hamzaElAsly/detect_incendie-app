<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class FactorySetting extends Model
{
    use BelongsToFactory,HasUlids;

    protected $fillable = ['key', 'value', 'type'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
