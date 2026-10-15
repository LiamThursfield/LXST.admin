<?php

namespace App\Http\Requests\Tenant\Admin\Tracker;

use App\Models\User;
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
            // The user's local wall time, defaulting to now; past dates backfill,
            // future dates (on the user's clock) are not allowed.
            'occurred_at' => ['nullable', 'date', 'before_or_equal:'.$this->localNow()],
            'label' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * The user's current wall-clock time, comparable with the submitted value.
     */
    private function localNow(): string
    {
        /** @var User $user */
        $user = $this->user();

        return $user->localNow()->toDateTimeString();
    }
}
