<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Camera extends Model
{
    use BelongsToFactory,HasUlids,SoftDeletes;

    protected $fillable = ['zone_id', 'name', 'reference', 'brand', 'model', 'serial_number', 'stream_type', 'stream_url', 'status', 'is_active', 'fire_threshold', 'smoke_threshold', 'last_seen_at', 'last_heartbeat_at', 'last_frame_at', 'latency_ms', 'fps', 'resolution', 'edge_agent_version', 'model_version', 'last_error_code', 'last_error_message', 'last_api_client_id', 'installed_at', 'metadata'];

    protected $hidden = ['stream_url'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'fire_threshold' => 'decimal:4', 'smoke_threshold' => 'decimal:4', 'last_seen_at' => 'datetime', 'last_heartbeat_at' => 'datetime', 'last_frame_at' => 'datetime', 'installed_at' => 'datetime', 'metadata' => 'array', 'stream_url' => 'encrypted'];
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function credential()
    {
        return $this->hasOne(CameraCredential::class);
    }

    public function healthLogs()
    {
        return $this->hasMany(CameraHealthLog::class);
    }

    public function fireEvents()
    {
        return $this->hasMany(FireEvent::class);
    }

    public function lastApiClient()
    {
        return $this->belongsTo(ApiClient::class, 'last_api_client_id');
    }
}
