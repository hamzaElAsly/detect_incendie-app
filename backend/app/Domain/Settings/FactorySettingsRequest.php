<?php

namespace App\Domain\Settings;

use Illuminate\Foundation\Http\FormRequest;

class FactorySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $factory = $this->attributes->get('factory');

        return $factory && $this->user()?->hasPermission('settings.update', $factory);
    }

    public function rules(): array
    {
        return ['timezone' => ['sometimes', 'timezone'], 'camera_offline_threshold_seconds' => ['sometimes', 'integer', 'between:30,3600'], 'default_fire_threshold' => ['sometimes', 'numeric', 'between:0,1'], 'default_smoke_threshold' => ['sometimes', 'numeric', 'between:0,1'], 'notification_channels' => ['sometimes', 'array'], 'notification_channels.*' => ['in:email,database,whatsapp,sms,push,websocket'], 'alert_cooldown' => ['sometimes', 'integer', 'between:0,86400'], 'required_positive_frames' => ['sometimes', 'integer', 'between:1,1000'], 'retention_days' => ['sometimes', 'integer', 'between:1,3650']];
    }
}
