<?php

use App\Models\Crm\OrganisationUnit;

test('the company and location are derived from the parent chain', function () {
    $company = OrganisationUnit::factory()->company()->create();
    $location = OrganisationUnit::factory()->location($company)->create();
    $department = OrganisationUnit::factory()->department($location)->create();
    $subDepartment = OrganisationUnit::factory()->department($department)->create();

    expect($subDepartment->company()->is($company))->toBeTrue()
        ->and($subDepartment->location()->is($location))->toBeTrue()
        ->and($location->company()->is($company))->toBeTrue()
        ->and($company->company()->is($company))->toBeTrue()
        ->and($company->location())->toBeNull();
});

test('isDescendantOf follows the whole parent chain', function () {
    $company = OrganisationUnit::factory()->company()->create();
    $location = OrganisationUnit::factory()->location($company)->create();
    $department = OrganisationUnit::factory()->department($location)->create();

    expect($department->isDescendantOf($company))->toBeTrue()
        ->and($company->isDescendantOf($department))->toBeFalse();
});

test('slugs are generated from the name and kept unique', function () {
    $first = OrganisationUnit::factory()->create(['name' => 'Acme Ltd', 'slug' => null]);
    $second = OrganisationUnit::factory()->create(['name' => 'Acme Ltd', 'slug' => null]);

    expect($first->slug)->toBe('acme-ltd')
        ->and($second->slug)->toBe('acme-ltd-2');
});
