<?php

namespace App\Http\Requests\Tenant\Admin\Bookings;

use App\Enums\Bookings\HireUnit;
use App\Enums\Bookings\MessageChannel;
use App\Enums\Bookings\ServiceOptionType;
use App\Enums\Bookings\WaitlistStatus;
use App\Enums\BookingType;
use App\Enums\Crm\OrganisationUnitType;
use App\Enums\TenantModule;
use App\Models\Bookings\Booking;
use App\Models\Bookings\Service;
use App\Models\Bookings\WaitlistEntry;
use App\Models\Crm\OrganisationUnit;
use App\Services\Messaging\PhoneNumber;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Booking::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request. What's needed depends on how the service is
     * booked: at a time, on a session, or with no time yet for an enquiry.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $service = $this->service();
        $atTime = $service !== null && $service->booking_type !== BookingType::Enquiry && ! $service->booking_type->usesSessions();
        $needsTime = $atTime && ! $this->boolean('walk_in');
        $onSession = $service !== null && $service->booking_type->usesSessions();

        return [
            'service_id' => ['required', 'integer', Rule::exists(Service::class, 'id')->withoutTrashed()],
            'location_id' => [
                Rule::excludeIf(! (tenant()?->hasModule(TenantModule::Crm) ?? false)),
                'nullable',
                'integer',
                Rule::exists(OrganisationUnit::class, 'id')->where('type', OrganisationUnitType::Location->value),
                $this->offeredAtLocationRule(),
            ],
            // A walk-in starts now, and only something booked at a time can be one
            'walk_in' => [Rule::excludeIf(! $atTime), 'boolean'],
            // Repeating is only for something booked ahead at a time
            'repeat_weeks' => [Rule::excludeIf(! $needsTime), 'nullable', 'integer', 'between:1,4'],
            'repeat_times' => [Rule::excludeIf(! $needsTime || ! $this->filled('repeat_weeks')), 'required', 'integer', 'between:2,52'],
            'waitlist_entry_id' => ['nullable', 'integer', Rule::exists(WaitlistEntry::class, 'id')->where('status', WaitlistStatus::Waiting->value)],
            'date' => [Rule::requiredIf($needsTime), Rule::excludeIf(! $needsTime), 'date_format:Y-m-d'],
            'time' => [Rule::requiredIf($needsTime), Rule::excludeIf(! $needsTime), 'date_format:H:i'],
            'session_id' => [Rule::requiredIf($onSession), Rule::excludeIf(! $onSession), 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100000', $this->quantityRule()],
            'option_ids' => ['nullable', 'array', $this->oneVariationRule()],
            'option_ids.*' => ['integer', Rule::exists('bookings_service_options', 'id')->where('service_id', $service->id ?? 0)],
            'resource_ids' => ['nullable', 'array'],
            'resource_ids.*' => ['integer'],
            'ignore_rules' => ['boolean'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            // Null uses the contact's preference, or email
            'channel' => ['nullable', Rule::enum(MessageChannel::class)],
            'customer_phone' => [
                Rule::requiredIf(in_array($this->input('channel'), [MessageChannel::Sms->value, MessageChannel::WhatsApp->value], true)),
                'nullable',
                'string',
                'max:50',
                $this->internationalPhoneRule(),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function service(): ?Service
    {
        return Service::query()->find($this->integer('service_id'));
    }

    public function location(): ?OrganisationUnit
    {
        $id = $this->validated('location_id');

        return is_numeric($id) ? OrganisationUnit::query()->find((int) $id) : null;
    }

    /**
     * Texts and WhatsApp need a number in international format, e.g. +447700900123
     */
    protected function internationalPhoneRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $wantsPhone = in_array($this->input('channel'), [MessageChannel::Sms->value, MessageChannel::WhatsApp->value], true);

            if ($wantsPhone && PhoneNumber::international(is_string($value) ? $value : null) === null) {
                $fail(__('bookings.bookings.validation.international_phone'));
            }
        };
    }

    protected function offeredAtLocationRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $location = is_numeric($value) ? OrganisationUnit::query()->find((int) $value) : null;
            $service = $this->service();

            if ($location !== null && $service !== null && ! Service::query()->whereKey($service->id)->offeredAt($location)->exists()) {
                $fail(__('bookings.bookings.validation.not_at_location'));
            }
        };
    }

    /**
     * A party or hire has to be within what the service allows
     */
    protected function quantityRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $service = $this->service();

            if ($service === null) {
                return;
            }

            [$min, $max] = $service->hire_unit instanceof HireUnit
                ? [$service->min_hire_units, $service->max_hire_units]
                : [$service->min_party_size, $service->max_party_size];

            if (($min !== null && $value < $min) || ($max !== null && $value > $max)) {
                $fail(__('bookings.bookings.validation.quantity_range', ['min' => $min ?? 1, 'max' => $max ?? '∞']));
            }
        };
    }

    protected function oneVariationRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $variations = $this->service()?->options()
                ->whereKey(is_array($value) ? $value : [])
                ->where('type', ServiceOptionType::Variation)
                ->count();

            if ($variations > 1) {
                $fail(__('bookings.bookings.validation.one_variation'));
            }
        };
    }
}
