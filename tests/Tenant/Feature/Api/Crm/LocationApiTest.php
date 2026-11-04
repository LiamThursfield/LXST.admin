<?php

use App\Enums\Cms\TemplateFieldType;
use App\Enums\TenantModule;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Models\Crm\Address;
use App\Models\Crm\OpeningHour;
use App\Models\Crm\OpeningHourException;
use App\Models\Crm\OrganisationUnit;
use App\Services\Cms\Api\ContentPresenter;
use App\Services\Cms\ContentRules;
use Illuminate\Support\Facades\Validator;
use Illuminate\Testing\Fluent\AssertableJson;

test('a location shows its hours, exceptions, whether it is open and its schema.org hours', function () {
    $this->travelTo('2026-11-02 12:00:00');

    $location = OrganisationUnit::factory()->location()->create(['slug' => 'high-street', 'name' => 'High Street']);
    Address::factory()->for($location, 'addressable')->create(['postcode' => 'M1 1AA']);
    OpeningHour::factory()->for($location, 'organisationUnit')->create(['day_of_week' => 1]);
    OpeningHourException::factory()->for($location, 'organisationUnit')->create(['name' => 'Christmas', 'starts_on' => '2026-12-25', 'ends_on' => '2026-12-25']);
    OpeningHourException::factory()->for($location, 'organisationUnit')->create(['name' => 'Last year', 'starts_on' => '2025-12-25', 'ends_on' => '2025-12-25']);

    $this->getJson(route('api.v1.crm.locations.show', 'high-street'))
        ->assertOk()
        ->assertJsonPath('data.name', 'High Street')
        ->assertJsonPath('data.location_type', 'premises')
        ->assertJsonPath('data.address.postcode', 'M1 1AA')
        ->assertJsonPath('data.opening_hours.0.day_of_week', 1)
        ->assertJsonPath('data.opening_hours.0.periods.0.opens_at', '09:00')
        ->assertJsonCount(7, 'data.opening_hours')
        ->assertJsonCount(1, 'data.exceptions')
        ->assertJsonPath('data.exceptions.0.name', 'Christmas')
        ->assertJsonPath('data.status.open_now', true)
        ->assertJsonPath('data.status.closes_at', '2026-11-02T17:00:00+00:00')
        ->assertJsonPath('data.schema_org.openingHoursSpecification.0.dayOfWeek', ['Monday']);
});

test('open now follows the clock, so it is not cached', function () {
    $location = OrganisationUnit::factory()->location()->create();
    OpeningHour::factory()->for($location, 'organisationUnit')->create(['day_of_week' => 1]);

    $this->travelTo('2026-11-02 12:00:00');
    $this->getJson(route('api.v1.crm.locations.show', $location->slug))->assertJsonPath('data.status.open_now', true);

    $this->travelTo('2026-11-02 18:00:00');
    $this->getJson(route('api.v1.crm.locations.show', $location->slug))->assertJsonPath('data.status.open_now', false);
});

test('a mobile or online location keeps its address private, and a mobile one shows its areas', function () {
    $mobile = OrganisationUnit::factory()->mobile(serviceAreas: ['M1', 'SK1'])->create();
    Address::factory()->for($mobile, 'addressable')->create();
    $online = OrganisationUnit::factory()->online()->create();
    Address::factory()->for($online, 'addressable')->create();

    $this->getJson(route('api.v1.crm.locations.show', $mobile->slug))
        ->assertJsonPath('data.address', null)
        ->assertJsonPath('data.service_areas', ['M1', 'SK1']);

    $this->getJson(route('api.v1.crm.locations.show', $online->slug))->assertJsonPath('data.address', null);
});

test('the list has only locations, and nothing private', function () {
    OrganisationUnit::factory()->company()->create();
    $location = OrganisationUnit::factory()->location()->create(['name' => 'High Street', 'notification_emails' => ['staff@example.test']]);
    OrganisationUnit::factory()->department($location)->create();

    $this->getJson(route('api.v1.crm.locations.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'High Street')
        ->assertJson(fn (AssertableJson $json) => $json->missing('data.0.notification_emails')->etc());
});

test('a unit that is not a location is not found', function () {
    $company = OrganisationUnit::factory()->company()->create();

    $this->getJson(route('api.v1.crm.locations.show', $company->slug))->assertNotFound();
});

test('the API needs the CRM module', function () {
    $location = OrganisationUnit::factory()->location()->create();
    tenant()->update(['modules' => [TenantModule::Cms]]);

    $this->getJson(route('api.v1.crm.locations.index'))->assertNotFound();
    $this->getJson(route('api.v1.crm.locations.show', $location->slug))->assertNotFound();
});

describe('the template field', function () {
    /**
     * @return array<string, mixed>
     */
    function presentHours(mixed $value): array
    {
        $template = Template::factory()->create();
        TemplateField::factory()->for($template)->create(['type' => TemplateFieldType::CrmOpeningHours, 'slug' => 'hours']);

        return app(ContentPresenter::class)->present($template->load('fields'), ['hours' => $value]);
    }

    test('it gives a page the hours and where to ask if it is open, without a cached answer', function () {
        $location = OrganisationUnit::factory()->location()->create(['slug' => 'high-street']);
        OpeningHour::factory()->for($location, 'organisationUnit')->create(['day_of_week' => 1]);

        $hours = presentHours($location->id)['hours'];

        expect($hours['slug'])->toBe('high-street')
            ->and($hours['opening_hours'][0]['periods'])->toHaveCount(1)
            ->and($hours['schema_org']['openingHoursSpecification'])->toHaveCount(1)
            ->and($hours['api_url'])->toBe(route('api.v1.crm.locations.show', 'high-street'))
            ->and($hours)->not->toHaveKey('status');
    });

    test('it is empty without CRM, or for a unit that is no longer a location', function () {
        $location = OrganisationUnit::factory()->location()->create();
        $company = OrganisationUnit::factory()->company()->create();

        expect(presentHours($company->id)['hours'])->toBeNull();

        tenant()->update(['modules' => [TenantModule::Cms]]);

        expect(presentHours($location->id)['hours'])->toBeNull();
    });

    test('only a location can be chosen', function () {
        $template = Template::factory()->create();
        TemplateField::factory()->for($template)->create(['type' => TemplateFieldType::CrmOpeningHours, 'slug' => 'hours']);
        $location = OrganisationUnit::factory()->location()->create();
        $company = OrganisationUnit::factory()->company()->create();

        $rules = app(ContentRules::class)->rules($template->load('fields'));

        expect(Validator::make(['content' => ['hours' => $company->id]], $rules)->fails())->toBeTrue()
            ->and(Validator::make(['content' => ['hours' => $location->id]], $rules)->fails())->toBeFalse();
    });
});
