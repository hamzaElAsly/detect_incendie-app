<?php

namespace App\Http\Controllers\Web\Requests;

use App\Models\ApiClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApiClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $f = $this->attributes->get('factory');

        return $f && $this->user()->hasPermission('api_clients.manage', $f);
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'allowed_ips_text' => ['nullable', 'string', 'max:2000'], 'scopes' => ['sometimes', 'array', 'min:1'], 'scopes.*' => ['string', Rule::in(ApiClient::SCOPES)], 'expires_at' => ['nullable', 'date', 'after:now']];
    }

    public function attributesData(): array
    {
        return ['name' => $this->validated('name'), 'allowed_ips' => collect(preg_split('/[\s,]+/', (string) $this->validated('allowed_ips_text')))->filter()->unique()->values()->all() ?: null, 'scopes' => $this->validated('scopes', ['camera:heartbeat']), 'expires_at' => $this->validated('expires_at')];
    }
}
