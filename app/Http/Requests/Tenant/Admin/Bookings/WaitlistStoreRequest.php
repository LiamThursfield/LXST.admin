<?php

namespace App\Http\Requests\Tenant\Admin\Bookings;

use App\Enums\BookingType;
use App\Enums\Crm\OrganisationUnitType;
use App\Enums\TenantModule;
use App\Models\Bookings\Service;
use App\Models\Bookings\ServiceSession;
use App\Models\Bookings\WaitlistEntry;
use App\Models\Crm\OrganisationUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WaitlistStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', WaitlistEntry::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request. A class or event waits for a place on a
     * timetable entry; anything else booked at a time waits for any time on a date. Enquiries have
     * no time to wait for.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $service = Service::query()->find($this->integer('service_id'));
        $onSession = $service !== null && $service->booking_type->usesSessions();

        return [
            'service_id' => [
                'required',
                'integer',
                Rule::exists(Service::class, 'id')->withoutTrashed()->whereNot('booking_type', BookingType::Enquiry->value),
            ],
            'session_id' => [Rule::requiredIf($onSession), Rule::excludeIf(! $onSession), 'integer', Rule::exists(ServiceSession::class, 'id')->where('service_id', $service->id ?? 0)],
            'wanted_on' => [Rule::requiredIf(! $onSession), Rule::excludeIf($onSession), 'date_format:Y-m-d', 'after_or_equal:today'],
            'location_id' => [
                Rule::excludeIf(! (tenant()?->hasModule(TenantModule::Crm) ?? false)),
                'nullable',
                'integer',
                Rule::exists(OrganisationUnit::class, 'id')->where('type', OrganisationUnitType::Location->value),
            ],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
