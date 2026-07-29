<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use BelongsToFactory,HasUlids;

    protected $fillable = ['plan_id', 'status', 'trial_ends_at', 'starts_at', 'ends_at', 'metadata'];

    protected function casts(): array
    {
        return ['trial_ends_at' => 'datetime', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'metadata' => 'array'];
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
