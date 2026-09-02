<?php

namespace App\Domain\Detections;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EdgeDetectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_uuid' => ['required', 'uuid'],
            'detected_at' => ['required', 'date', 'before_or_equal:'.now()->addMinute()->toIso8601String(), 'after_or_equal:'.now()->subMinutes(5)->toIso8601String()],
            'class' => ['required', Rule::in(['fire', 'smoke'])],
            'class_id' => ['required', 'integer', Rule::in([0, 1])],
            'confidence' => ['required', 'numeric', 'between:0,1'],
            'bounding_box' => ['required', 'array:x,y,width,height'],
            'bounding_box.x' => ['required', 'numeric', 'between:0,1'],
            'bounding_box.y' => ['required', 'numeric', 'between:0,1'],
            'bounding_box.width' => ['required', 'numeric', 'gt:0', 'max:1'],
            'bounding_box.height' => ['required', 'numeric', 'gt:0', 'max:1'],
            'frame_width' => ['required', 'integer', 'between:16,16384'],
            'frame_height' => ['required', 'integer', 'between:16,16384'],
            'model_name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'model_version' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'inference_ms' => ['required', 'numeric', 'min:0', 'max:600000'],
            'confirmation_frames' => ['required', 'integer', 'between:1,1000'],
            'snapshot' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
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

        $box = $this->validated('bounding_box');
        if ((float) $box['x'] + (float) $box['width'] > 1 || (float) $box['y'] + (float) $box['height'] > 1) {
            throw ValidationException::withMessages([
                'bounding_box' => 'The normalized bounding box must remain inside the frame.',
            ]);
        }

        $expectedClassId = ['fire' => 0, 'smoke' => 1][$this->validated('class')];
        if ((int) $this->validated('class_id') !== $expectedClassId) {
            throw ValidationException::withMessages([
                'class_id' => 'The class_id must match the fire/smoke model mapping.',
            ]);
        }
    }
}
