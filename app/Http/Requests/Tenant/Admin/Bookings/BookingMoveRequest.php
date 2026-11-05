<?php

namespace App\Http\Requests\Tenant\Admin\Bookings;

use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingMoveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Booking $booking */
        $booking = $this->route('booking');

        return $this->user()?->can('update', $booking) ?? false;
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
            'resource_id' => ['nullable', 'integer', Rule::exists(BookableResource::class, 'id')->withoutTrashed()],
        ];
    }
}
