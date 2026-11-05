<?php

namespace App\Http\Requests\Tenant\Admin\Bookings;

use App\Enums\Bookings\BookingStatus;
use App\Models\Bookings\Booking;
use App\Services\Industry\Terminology;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->booking()) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(BookingStatus::class), $this->allowedMoveRule()],
        ];
    }

    protected function booking(): Booking
    {
        /** @var Booking $booking */
        $booking = $this->route('booking');

        return $booking;
    }

    protected function allowedMoveRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $booking = $this->booking();
            $status = BookingStatus::tryFrom((string) $value);
            $terminology = app(Terminology::class);

            if ($status === null) {
                return;
            }

            if (! in_array($status, $booking->status->nextStatuses(), true)) {
                $fail($terminology->translate('bookings.bookings.validation.cannot_move', [
                    'from' => $booking->status->label(),
                    'to' => $status->label(),
                ]));

                return;
            }

            if ($booking->needsTime() && $status !== BookingStatus::Cancelled) {
                $fail($terminology->translate('bookings.bookings.validation.needs_time'));
            }
        };
    }
}
