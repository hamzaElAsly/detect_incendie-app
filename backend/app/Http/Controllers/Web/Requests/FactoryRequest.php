<?php

namespace App\Http\Controllers\Web\Requests;

use App\Models\Factory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FactoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->factory ? 'update' : 'create', $this->factory ?: Factory::class);
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('factories')->ignore($this->factory)], 'reference' => ['nullable', 'string', 'max:100', Rule::unique('factories')->ignore($this->factory)], 'address' => ['nullable', 'string', 'max:2000'], 'city' => ['nullable', 'string', 'max:100'], 'country' => ['nullable', 'string', 'max:100'], 'phone' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:255'], 'timezone' => ['required', 'timezone'], 'status' => ['required', Rule::in(['active', 'inactive'])], 'is_active' => ['nullable', 'boolean']];
    }
}
