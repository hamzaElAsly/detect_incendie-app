<?php

namespace App\Domain\Health;

use App\Models\Camera;
use App\Models\FactorySetting;

class CameraStatusService
{
    public function threshold(Camera $camera): int
    {
        $custom = FactorySetting::forFactory($camera->factory_id)
            ->where('key', 'camera_offline_threshold_seconds')->value('value');

        return max(30, (int) ($custom ?? config('camera_health.offline_threshold_seconds', 120)));
    }

    public function status(Camera $camera): string
    {
        if ($camera->is_active === false) {
            return 'disabled';
        }
        if (! $camera->last_heartbeat_at) {
            return 'unknown';
        }
        if ($camera->last_heartbeat_at->lt(now()->subSeconds($this->threshold($camera)))) {
            return 'offline';
        }
        if ($camera->last_error_code || ($camera->latency_ms !== null && $camera->latency_ms > 2000)) {
            return 'degraded';
        }

        return 'online';
    }
}
