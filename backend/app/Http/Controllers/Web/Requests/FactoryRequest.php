<?php

namespace App\Http\Controllers\Web\Requests;

use App\Models\Factory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FactoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->routeIs('factory-settings.update')) {
            $factory = $this->attributes->get('factory');

            return $factory && $this->user()->can('update', $factory);
        }

        return $this->user()->can($this->factory ? 'update' : 'create', $this->factory ?: Factory::class);
    }

    public function rules(): array
    {
        $factory = $this->factory ?: $this->attributes->get('factory');

        return [
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('factories')->ignore($factory)],
            'reference' => ['nullable', 'string', 'max:100', Rule::unique('factories')->ignore($factory)],
            'installation_identifier' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'timezone' => ['required', 'timezone'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
