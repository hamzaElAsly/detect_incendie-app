<?php

namespace App\Http\Controllers\Web\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', Rule::unique('users')->ignore($this->user())], 'phone' => ['nullable', 'string', 'max:40'], 'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'], 'timezone' => ['sometimes', 'timezone'], 'preferences' => ['sometimes', 'array:theme,locale,compact_sidebar,email_notifications'], 'preferences.theme' => ['required_with:preferences', Rule::in(['system', 'light', 'dark'])], 'preferences.locale' => ['required_with:preferences', Rule::in(['fr', 'en'])], 'preferences.compact_sidebar' => ['nullable', 'boolean'], 'preferences.email_notifications' => ['nullable', 'boolean']];
    }
}
