<?php

namespace App\Http\Requests\Tenant\Admin\Bookings;

use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Booking;
use App\Models\Crm\OrganisationUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingCalendarRequest extends FormRequest
{
    public const string VIEW_DAY = 'day';

    public const string VIEW_WEEK = 'week';

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
            'view' => ['nullable', Rule::in([self::VIEW_DAY, self::VIEW_WEEK])],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'location_id' => ['nullable', 'integer', Rule::exists(OrganisationUnit::class, 'id')],
            'resource_id' => ['nullable', 'integer', Rule::exists(BookableResource::class, 'id')],
        ];
    }
}
