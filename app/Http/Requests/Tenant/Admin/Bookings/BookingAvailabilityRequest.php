<?php

namespace App\Http\Requests\Tenant\Admin\Bookings;

use App\Models\Bookings\Booking;
use App\Models\Bookings\Service;
use App\Models\Crm\OrganisationUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingAvailabilityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Booking::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', Rule::exists(Service::class, 'id')->withoutTrashed()],
            'location_id' => ['nullable', 'integer', Rule::exists(OrganisationUnit::class, 'id')],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'option_ids' => ['nullable', 'array'],
            'option_ids.*' => ['integer'],
            'resource_ids' => ['nullable', 'array'],
            'resource_ids.*' => ['integer'],
        ];
    }
}
