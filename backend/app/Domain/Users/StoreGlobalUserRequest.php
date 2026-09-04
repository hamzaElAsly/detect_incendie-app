<?php

namespace App\Domain\Users;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreGlobalUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('system_admin');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')], 'phone' => ['nullable', 'string', 'max:40'], 'password' => ['required', 'confirmed', Password::defaults()], 'role' => ['required', Rule::exists('roles', 'name')->where('is_platform', true)], 'is_active' => ['nullable', 'boolean']];
    }

    public function assignableRole(): Role
    {
        $role = Role::where('name', $this->validated('role'))->where('is_platform', true)->firstOrFail();
        abort_unless($role->name === 'system_admin', 403);

        return $role;
    }
}
