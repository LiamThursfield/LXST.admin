<?php

use App\Enums\Crm\OrganisationUnitType;
use App\Enums\TenantModule;
use App\Models\Crm\Address;
use App\Models\Crm\OrganisationUnit;
use App\Services\Authorisation\Enums\CrmPermission;
use Inertia\Testing\AssertableInertia as Assert;

function validOrganisationUnitData(array $overrides = []): array
{
    return [
        'type' => 'company',
        'parent_id' => null,
        'name' => 'Acme Ltd',
        'slug' => '',
        'email' => 'hello@acme.test',
        'telephone' => '0161 000 0000',
        'mobile_phone' => '',
        'socials' => ['linkedin' => 'https://www.linkedin.com/company/acme', 'facebook' => ''],
        'notification_emails' => ['sales@acme.test'],
        'address' => [
            'postcode' => 'M1 1AA',
            'line_1' => '1 High Street',
            'line_2' => null,
            'line_3' => null,
            'town_city' => 'Manchester',
            'county' => null,
            'country' => 'United Kingdom',
        ],
        ...$overrides,
    ];
}

describe('access', function () {
    test('guests are redirected to the login page', function () {
        $this->get(route('admin.crm.organisation-units.index'))->assertRedirect(route('login'));
    });

    test('users without the view permission cannot see the index', function () {
        $this->actingAs($this->createUserWithPermissions([]));

        $this->get(route('admin.crm.organisation-units.index'))->assertForbidden();
    });

    test('users with the view permission can see the index but not create', function () {
        $this->actingAs($this->createUserWithPermissions([CrmPermission::ViewOrganisationUnits]));
        OrganisationUnit::factory()->create();

        $this->get(route('admin.crm.organisation-units.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/crm/organisation-unit/Index')
                ->has('organisationUnits.data', 1)
                ->where('canCreate', false)
            );

        $this->get(route('admin.crm.organisation-units.create'))->assertForbidden();
        $this->post(route('admin.crm.organisation-units.store'), validOrganisationUnitData())->assertForbidden();
    });

    test('the pages return not found when the tenant does not have the CRM module', function () {
        $this->actingAs($this->createUserWithPermissions(CrmPermission::cases()));
        tenant()->update(['modules' => [TenantModule::Tracker]]);

        $this->get(route('admin.crm.organisation-units.index'))->assertNotFound();
    });
});

describe('index', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CrmPermission::cases()));
    });

    test('it can be filtered by name and type', function () {
        $company = OrganisationUnit::factory()->company()->create(['name' => 'Acme Ltd']);
        OrganisationUnit::factory()->location($company)->create(['name' => 'Acme Leeds']);
        OrganisationUnit::factory()->company()->create(['name' => 'Globex']);

        $this->get(route('admin.crm.organisation-units.index', ['filter[name]' => 'Acme']))
            ->assertInertia(fn (Assert $page) => $page->has('organisationUnits.data', 2));

        $this->get(route('admin.crm.organisation-units.index', ['filter[name]' => 'Acme', 'filter[type]' => 'location']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('organisationUnits.data', 1)
                ->where('organisationUnits.data.0.name', 'Acme Leeds')
                ->where('organisationUnits.data.0.parent.name', 'Acme Ltd')
            );
    });

    test('the delete action is only offered for units without children', function () {
        $company = OrganisationUnit::factory()->company()->create(['name' => 'Acme Ltd']);
        OrganisationUnit::factory()->location($company)->create(['name' => 'Acme Leeds']);

        $this->get(route('admin.crm.organisation-units.index'))
            ->assertInertia(function (Assert $page) {
                $units = collect($page->toArray()['props']['organisationUnits']['data'])->keyBy('name');
                $actionLabels = fn (string $name) => collect($units[$name]['actions'])->pluck('label');

                expect($actionLabels('Acme Ltd'))->not->toContain(__('common.actions.delete'))
                    ->and($actionLabels('Acme Leeds'))->toContain(__('common.actions.delete'));
            });
    });
});

describe('store', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CrmPermission::cases()));
    });

    test('it creates a company with its address, generating the slug and dropping blank socials', function () {
        $this->post(route('admin.crm.organisation-units.store'), validOrganisationUnitData())
            ->assertRedirect(route('admin.crm.organisation-units.index'));

        $unit = OrganisationUnit::query()->sole();

        expect($unit->type)->toBe(OrganisationUnitType::Company)
            ->and($unit->slug)->toBe('acme-ltd')
            ->and($unit->socials)->toBe(['linkedin' => 'https://www.linkedin.com/company/acme'])
            ->and($unit->notification_emails)->toBe(['sales@acme.test'])
            ->and($unit->address->postcode)->toBe('M1 1AA')
            ->and($unit->address->town_city)->toBe('Manchester');
    });

    test('an address with every field blank is not saved', function () {
        $data = validOrganisationUnitData([
            'address' => array_fill_keys(Address::FIELDS, ''),
        ]);

        $this->post(route('admin.crm.organisation-units.store'), $data)->assertSessionHasNoErrors();

        expect(OrganisationUnit::query()->sole()->address)->toBeNull();
    });

    test('a partly filled address needs a postcode and first line', function () {
        $data = validOrganisationUnitData([
            'address' => [...array_fill_keys(Address::FIELDS, ''), 'town_city' => 'Leeds'],
        ]);

        $this->post(route('admin.crm.organisation-units.store'), $data)
            ->assertSessionHasErrors(['address.postcode', 'address.line_1']);
    });

    test('a postcode longer than ten characters is rejected', function () {
        $data = validOrganisationUnitData();
        $data['address']['postcode'] = 'ABCDE 12345X';

        $this->post(route('admin.crm.organisation-units.store'), $data)
            ->assertSessionHasErrors('address.postcode');
    });

    test('a location must be part of a company', function () {
        $company = OrganisationUnit::factory()->company()->create();
        $otherLocation = OrganisationUnit::factory()->location($company)->create();

        $this->post(route('admin.crm.organisation-units.store'), validOrganisationUnitData(['type' => 'location', 'parent_id' => null]))
            ->assertSessionHasErrors('parent_id');

        $this->post(route('admin.crm.organisation-units.store'), validOrganisationUnitData(['type' => 'location', 'parent_id' => $otherLocation->id]))
            ->assertSessionHasErrors('parent_id');

        $this->post(route('admin.crm.organisation-units.store'), validOrganisationUnitData(['type' => 'location', 'parent_id' => $company->id]))
            ->assertSessionHasNoErrors();
    });

    test('a department can be part of a location or another department, but not a company', function () {
        $company = OrganisationUnit::factory()->company()->create();
        $location = OrganisationUnit::factory()->location($company)->create();
        $department = OrganisationUnit::factory()->department($location)->create();

        $this->post(route('admin.crm.organisation-units.store'), validOrganisationUnitData(['type' => 'department', 'parent_id' => $company->id, 'name' => 'A']))
            ->assertSessionHasErrors('parent_id');

        $this->post(route('admin.crm.organisation-units.store'), validOrganisationUnitData(['type' => 'department', 'parent_id' => $location->id, 'name' => 'B']))
            ->assertSessionHasNoErrors();

        $this->post(route('admin.crm.organisation-units.store'), validOrganisationUnitData(['type' => 'department', 'parent_id' => $department->id, 'name' => 'C']))
            ->assertSessionHasNoErrors();
    });

    test('slugs must be unique and unknown social networks are rejected', function () {
        OrganisationUnit::factory()->create(['slug' => 'taken']);

        $this->post(route('admin.crm.organisation-units.store'), validOrganisationUnitData(['slug' => 'taken']))
            ->assertSessionHasErrors('slug');

        $this->post(route('admin.crm.organisation-units.store'), validOrganisationUnitData(['socials' => ['myspace' => 'https://myspace.test']]))
            ->assertSessionHasErrors('socials');
    });
});

describe('update', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CrmPermission::cases()));
    });

    test('it updates the unit and replaces its address', function () {
        $unit = OrganisationUnit::factory()->company()->create();
        Address::factory()->for($unit, 'addressable')->create(['postcode' => 'LS1 1AA']);

        $this->put(route('admin.crm.organisation-units.update', $unit), validOrganisationUnitData(['name' => 'Renamed Ltd']))
            ->assertRedirect(route('admin.crm.organisation-units.index'));

        $unit->refresh();

        expect($unit->name)->toBe('Renamed Ltd')
            ->and(Address::query()->count())->toBe(1)
            ->and($unit->address->postcode)->toBe('M1 1AA');
    });

    test('clearing the address removes it', function () {
        $unit = OrganisationUnit::factory()->company()->create();
        Address::factory()->for($unit, 'addressable')->create();

        $this->put(route('admin.crm.organisation-units.update', $unit), validOrganisationUnitData([
            'address' => array_fill_keys(Address::FIELDS, ''),
        ]))->assertSessionHasNoErrors();

        expect(Address::query()->count())->toBe(0);
    });

    test('a unit cannot be moved under itself or one of its descendants', function () {
        $company = OrganisationUnit::factory()->company()->create();
        $location = OrganisationUnit::factory()->location($company)->create();
        $department = OrganisationUnit::factory()->department($location)->create();
        $subDepartment = OrganisationUnit::factory()->department($department)->create();

        $this->put(route('admin.crm.organisation-units.update', $department), validOrganisationUnitData([
            'type' => 'department',
            'parent_id' => $department->id,
        ]))->assertSessionHasErrors('parent_id');

        $this->put(route('admin.crm.organisation-units.update', $department), validOrganisationUnitData([
            'type' => 'department',
            'parent_id' => $subDepartment->id,
        ]))->assertSessionHasErrors('parent_id');
    });
});

describe('destroy', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CrmPermission::cases()));
    });

    test('it deletes a unit and its address', function () {
        $unit = OrganisationUnit::factory()->company()->create();
        Address::factory()->for($unit, 'addressable')->create();

        $this->delete(route('admin.crm.organisation-units.destroy', $unit))->assertRedirect();

        expect(OrganisationUnit::query()->count())->toBe(0)
            ->and(Address::query()->count())->toBe(0);
    });

    test('a unit with children cannot be deleted', function () {
        $company = OrganisationUnit::factory()->company()->create();
        OrganisationUnit::factory()->location($company)->create();

        $this->delete(route('admin.crm.organisation-units.destroy', $company))
            ->assertSessionHasErrors('organisation_unit');

        expect($company->fresh())->not->toBeNull();
    });
});
