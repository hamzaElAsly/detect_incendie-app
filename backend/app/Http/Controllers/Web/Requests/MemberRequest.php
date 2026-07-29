<?php

namespace App\Http\Controllers\Web\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $factory = $this->attributes->get('factory');
        $member = $this->route('member');

        return $factory
            && (! $member || (! $member->is_super_admin && $member->belongsToFactory($factory)))
            && $this->user()->hasPermission($member ? 'users.update' : 'users.invite', $factory);
    }

    public function rules(): array
    {
        $member = $this->route('member');
        $emailRules = [$member ? 'sometimes' : 'required', 'email:rfc', 'max:255'];
        if ($member) {
            $emailRules[] = Rule::unique('users')->ignore($member);
        }

        return [
            'name' => [$member ? 'sometimes' : 'required', 'string', 'max:255'],
            'email' => $emailRules,
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::exists('roles', 'name')->where('is_platform', false)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }
    }
}
