<?php

namespace App\Http\Requests\Tenant\Admin\Tracker;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TaskEntryStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('logEntry', $this->route('task')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Expressed in the task's display unit; converted to base when stored.
            'value' => ['required', 'numeric', 'min:0'],
            // Defaults to now; past dates backfill, future dates are not allowed.
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now'],
            'label' => ['nullable', 'string', 'max:120'],
        ];
    }
}
