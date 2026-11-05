<?php

namespace App\Http\Requests\Tenant\Admin\Bookings\Concerns;

use App\Enums\Crm\OrganisationUnitType;
use App\Enums\TenantModule;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Service;
use App\Models\Crm\OrganisationUnit;
use Closure;
use Illuminate\Validation\Rule;

trait ValidatesServiceSession
{
    /**
     * The shared validation rules for creating or updating a timetable entry.
     *
     * @return array<string, mixed>
     */
    protected function sessionRules(): array
    {
        return [
            'service_id' => ['required', 'integer', Rule::exists(Service::class, 'id')->withoutTrashed(), $this->sessionServiceRule()],
            'location_id' => [
                Rule::excludeIf(! (tenant()?->hasModule(TenantModule::Crm) ?? false)),
                'nullable',
                'integer',
                Rule::exists(OrganisationUnit::class, 'id')->where('type', OrganisationUnitType::Location->value),
            ],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:43200'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'resource_ids' => ['nullable', 'array'],
            'resource_ids.*' => ['integer', 'distinct', Rule::exists(BookableResource::class, 'id')->withoutTrashed()],
        ];
    }

    protected function sessionServiceRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_numeric($value) && ! (Service::query()->find((int) $value)?->booking_type->usesSessions() ?? true)) {
                $fail(__('bookings.sessions.validation.wrong_service'));
            }
        };
    }
}
