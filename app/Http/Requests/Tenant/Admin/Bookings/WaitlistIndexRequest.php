<?php

namespace App\Http\Requests\Tenant\Admin\Bookings;

use App\Enums\Bookings\WaitlistStatus;
use App\Models\Bookings\WaitlistEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WaitlistIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', WaitlistEntry::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array'],
            'filter.status' => ['nullable', Rule::enum(WaitlistStatus::class)],
            'filter.session_id' => ['nullable', 'integer'],
        ];
    }
}
