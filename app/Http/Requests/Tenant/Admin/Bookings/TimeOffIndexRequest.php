<?php

namespace App\Http\Requests\Tenant\Admin\Bookings;

use App\Enums\Bookings\TimeOffStatus;
use App\Models\Bookings\TimeOff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TimeOffIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', TimeOff::class) ?? false;
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
            'filter.status' => ['nullable', Rule::enum(TimeOffStatus::class)],
        ];
    }
}
