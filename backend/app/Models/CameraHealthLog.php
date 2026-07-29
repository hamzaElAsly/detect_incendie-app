<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class CameraHealthLog extends Model
{
    use BelongsToFactory,HasUlids;

    public $timestamps = false;

    protected $fillable = ['camera_id', 'status', 'latency_ms', 'fps', 'last_frame_at', 'cpu_usage', 'gpu_usage', 'memory_usage', 'message', 'recorded_at'];

    protected function casts(): array
    {
        return ['last_frame_at' => 'datetime', 'recorded_at' => 'datetime'];
    }

    public function camera()
    {
        return $this->belongsTo(Camera::class);
    }
}
