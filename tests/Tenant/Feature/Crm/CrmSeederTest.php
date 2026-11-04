<?php

use App\Enums\Crm\LocationType;
use App\Enums\Crm\OrganisationUnitType;
use App\Models\Crm\Address;
use App\Models\Crm\Contact;
use App\Models\Crm\Form;
use App\Models\Crm\FormSubmission;
use App\Models\Crm\OpeningHour;
use App\Models\Crm\OpeningHourException;
use App\Models\Crm\OrganisationUnit;
use Database\Seeders\CrmSeeder;

it('seeds organisation units, contacts, a form and submissions, and is safe to re-run', function () {
    $this->seed(CrmSeeder::class);
    $this->seed(CrmSeeder::class);

    expect(OrganisationUnit::query()->count())->toBe(9)
        ->and(Contact::query()->count())->toBe(12)
        ->and(Form::query()->count())->toBe(1)
        ->and(Form::query()->sole()->fields()->count())->toBe(5)
        ->and(FormSubmission::query()->count())->toBe(5)
        ->and(FormSubmission::query()->where('is_spam', true)->count())->toBe(1)
        ->and(Contact::query()->where('is_spam', true)->count())->toBe(1)
        ->and(Address::query()->count())->toBe(4 + 8);
});

it('seeds a location of each type, with hours and exceptions to show', function () {
    $this->seed(CrmSeeder::class);
    $this->seed(CrmSeeder::class);

    $locations = OrganisationUnit::query()->where('type', OrganisationUnitType::Location)->get();

    expect($locations->map(fn (OrganisationUnit $location) => $location->location_type)->unique()->values()->all())
        ->toEqualCanonicalizing(LocationType::cases())
        ->and(OpeningHour::query()->count())->toBe(11 + 6 + 7)
        ->and(OpeningHourException::query()->count())->toBe(3)
        ->and(OpeningHourException::query()->min('starts_on'))->toBeGreaterThan(now()->toDateString())
        ->and($locations->firstWhere('slug', 'leeds-office')->service_areas)->toContain('LS1');
});
