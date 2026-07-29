<?php

namespace App\Domain\Settings;

use App\Models\Factory;
use App\Models\FactorySetting;
use Illuminate\Support\Facades\DB;

class FactorySettingsService
{
    private const TYPES = ['timezone' => 'string', 'default_fire_threshold' => 'float', 'default_smoke_threshold' => 'float', 'notification_channels' => 'array', 'alert_cooldown' => 'integer', 'required_positive_frames' => 'integer', 'retention_days' => 'integer', 'camera_offline_threshold_seconds' => 'integer'];

    public function all(Factory $factory): array
    {
        return FactorySetting::forFactory($factory)->pluck('value', 'key')->toArray();
    }

    public function update(Factory $factory, array $values): array
    {
        DB::transaction(function () use ($factory, $values) {
            foreach ($values as $key => $value) {
                $setting = FactorySetting::firstOrNew(['factory_id' => $factory->id, 'key' => $key]);
                $setting->forceFill(['factory_id' => $factory->id, 'key' => $key, 'value' => $value, 'type' => self::TYPES[$key]])->save();
            }
        });

        return $this->all($factory);
    }
}
