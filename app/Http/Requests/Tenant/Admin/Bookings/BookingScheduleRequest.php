<?php

namespace App\Http\Requests\Tenant\Admin\Bookings;

use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. Only an enquiry still waiting for a
     * time can be given one.
     */
    public function authorize(): bool
    {
        /** @var Booking $booking */
        $booking = $this->route('booking');

        return ($this->user()?->can('update', $booking) ?? false) && $booking->needsTime() && $booking->status->holdsTime();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:43200'],
            'resource_ids' => ['nullable', 'array'],
            'resource_ids.*' => ['integer', 'distinct', Rule::exists(BookableResource::class, 'id')->withoutTrashed()],
        ];
    }
}
