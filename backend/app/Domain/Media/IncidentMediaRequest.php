<?php

namespace App\Domain\Media;

use Illuminate\Foundation\Http\FormRequest;

class IncidentMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $factory = $this->route('factory');
        $event = $this->route('event');

        return $factory
            && $event
            && $event->factory_id === $factory->id
            && $this->user()?->hasPermission('events.create', $factory);
    }

    public function rules(): array
    {
        return ['image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'required_without:video'], 'video' => ['nullable', 'file', 'mimes:mp4,webm,mov', 'max:51200', 'required_without:image']];
    }
}
