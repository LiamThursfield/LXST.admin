<?php

namespace App\Http\Requests\Tenant\Admin\Bookings;

use App\Enums\Bookings\BookingStatus;
use App\Models\Bookings\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Booking::class) ?? false;
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
            'filter.customer_name' => ['nullable', Rule::string()],
            'filter.status' => ['nullable', Rule::enum(BookingStatus::class)],
        ];
    }
}
