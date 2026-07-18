<?php

namespace App\Domain\Recipients;

use App\Models\Factory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AlertRecipientValidator
{
    public function validate(Factory $factory, array $data): array
    {
        $channels = $data['channels'] ?? [];

        return Validator::make($data, [
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('factory_user', 'user_id')->where('factory_id', $factory->getKey()),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', Rule::requiredIf(in_array('email', $channels, true)), 'email:rfc', 'max:255'],
            'phone' => ['nullable', Rule::requiredIf(array_intersect(['sms', 'whatsapp'], $channels) !== []), 'string', 'max:40'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['required', 'distinct', Rule::in(['email', 'database', 'whatsapp', 'sms', 'push'])],
            'severity_min' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'is_active' => ['required', 'boolean'],
        ])->validate();
    }
}
