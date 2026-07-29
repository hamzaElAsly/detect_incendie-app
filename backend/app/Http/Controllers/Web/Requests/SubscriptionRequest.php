<?php

namespace App\Http\Controllers\Web\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('subscriptions.manage');
    }

    public function rules(): array
    {
        return ['factory_id' => ['required', Rule::exists('factories', 'id')], 'plan_id' => ['required', Rule::exists('plans', 'id')], 'status' => ['required', Rule::in(['trial', 'active', 'paused', 'cancelled', 'expired'])], 'trial_ends_at' => ['nullable', 'date'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at']];
    }
}
