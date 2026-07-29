<?php

namespace App\Http\Controllers\Web\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EventActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $factory = $this->attributes->get('factory');
        $event = $this->route('event');
        $ability = match ($this->route()->getName()) {
            'events.confirm' => 'events.confirm','events.false-alarm' => 'events.mark_false_alarm','events.resolve' => 'events.resolve',default => 'events.view'
        };

        return $factory && $event && $event->factory_id === $factory->id && $this->user()->hasPermission($ability, $factory);
    }

    public function rules(): array
    {
        return ['note' => ['nullable', 'string', 'max:1000']];
    }
}
