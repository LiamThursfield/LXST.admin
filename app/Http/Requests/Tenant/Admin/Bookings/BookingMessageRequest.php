<?php

namespace App\Http\Requests\Tenant\Admin\Bookings;

use App\Models\Bookings\Booking;
use Illuminate\Foundation\Http\FormRequest;

class BookingMessageRequest extends FormRequest
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
     * Get the validation rules that apply to the request. Kept to a few texts' worth.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:480'],
        ];
    }
}
