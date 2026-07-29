<?php

namespace App\Models;

use App\Enums\AlertStatus;
use App\Enums\Severity;
use App\Models\Concerns\BelongsToFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    use BelongsToFactory,HasUlids;

    protected $fillable = ['fire_event_id', 'type', 'severity', 'status', 'message', 'sent_at', 'acknowledged_at', 'acknowledged_by'];

    protected function casts(): array
    {
        return ['severity' => Severity::class, 'status' => AlertStatus::class, 'sent_at' => 'datetime', 'acknowledged_at' => 'datetime'];
    }

    public function fireEvent()
    {
        return $this->belongsTo(FireEvent::class);
    }

    public function acknowledgedBy()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
