<?php

namespace App\Http\Controllers\Web\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AlertAcknowledgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $f = $this->attributes->get('factory');
        $a = $this->route('alert');

        return $f && $a && $a->factory_id === $f->id && $this->user()->hasPermission('alerts.manage', $f);
    }

    public function rules(): array
    {
        return ['note' => ['nullable', 'string', 'max:1000']];
    }
}
