<?php

namespace App\Domain\Health;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(['online', 'degraded', 'error'])],
            'sent_at' => ['nullable', 'date', 'before_or_equal:tomorrow', 'after:'.now()->subDay()->toIso8601String()],
            'latency_ms' => ['nullable', 'integer', 'min:0', 'max:600000'],
            'fps' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'resolution' => ['nullable', 'string', 'regex:/^\d{2,5}x\d{2,5}$/', 'max:30'],
            'last_frame_at' => ['nullable', 'date', 'before_or_equal:tomorrow', 'after:'.now()->subDay()->toIso8601String()],
            'edge_agent_version' => ['nullable', 'string', 'max:100'],
            'model_version' => ['nullable', 'string', 'max:100'],
            'error_code' => ['nullable', 'string', 'max:100'],
            'error_message' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function passedValidation(): void
    {
        $unexpected = array_diff(array_keys($this->all()), array_keys($this->rules()));
        if ($unexpected !== []) {
            throw ValidationException::withMessages([
                'payload' => 'Unexpected fields: '.implode(', ', $unexpected),
            ]);
        }
    }
}
