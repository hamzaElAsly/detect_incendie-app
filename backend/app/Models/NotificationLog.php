<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = ['status', 'provider_response', 'recorded_at'];

    protected function casts(): array
    {
        return ['provider_response' => 'array', 'recorded_at' => 'datetime'];
    }

    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }
}
