<?php

namespace App\Http\Controllers\Web\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CameraRequest extends FormRequest
{
    public function authorize(): bool
    {
        $f = $this->attributes->get('factory');

        $camera = $this->route('camera');

        return $f
            && (! $camera || $camera->factory_id === $f->id)
            && $this->user()->hasPermission($camera ? 'cameras.update' : 'cameras.create', $f);
    }

    public function rules(): array
    {
        $factory = $this->attributes->get('factory');

        return ['name' => ['required', 'string', 'max:255'], 'reference' => ['required', 'string', 'max:100', Rule::unique('cameras')->where('factory_id', $factory->id)->ignore($this->camera)], 'zone_id' => ['nullable', Rule::exists('zones', 'id')->where('factory_id', $factory->id)], 'brand' => ['nullable', 'string', 'max:100'], 'model' => ['nullable', 'string', 'max:100'], 'serial_number' => ['nullable', 'string', 'max:150'], 'stream_type' => ['required', Rule::in(['rtsp', 'http', 'onvif'])], 'stream_url' => ['nullable', 'string', 'max:2048'], 'status' => ['required', Rule::in(['online', 'offline', 'maintenance', 'error'])], 'is_active' => ['nullable', 'boolean'], 'fire_threshold' => ['required', 'numeric', 'between:0,1'], 'smoke_threshold' => ['required', 'numeric', 'between:0,1']];
    }
}
