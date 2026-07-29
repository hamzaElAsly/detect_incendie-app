<?php

namespace App\Http\Controllers\Web\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        $f = $this->attributes->get('factory');

        $zone = $this->route('zone');

        return $f
            && (! $zone || $zone->factory_id === $f->id)
            && $this->user()->hasPermission($zone ? 'zones.update' : 'zones.create', $f);
    }

    public function rules(): array
    {
        $factory = $this->attributes->get('factory');

        return ['name' => ['required', 'string', 'max:255'], 'reference' => ['required', 'string', 'max:100', Rule::unique('zones')->where('factory_id', $factory->id)->ignore($this->zone)], 'description' => ['nullable', 'string', 'max:2000'], 'risk_level' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])], 'floor' => ['nullable', 'string', 'max:100'], 'status' => ['required', Rule::in(['active', 'inactive'])]];
    }
}
