<?php

use App\Services\Industry\Terminology;
use Database\Seeders\IndustrySeeder;

test('a tenant with no industry yet becomes the seeded business, using its own word', function () {
    $this->seed(IndustrySeeder::class);

    $tenant = tenant()->refresh();
    $terms = app(Terminology::class)->forTenant($tenant);

    expect($tenant->industry)->toBe(IndustrySeeder::INDUSTRY)
        ->and($terms['staff_member'])->toEqual(['singular' => 'DJ', 'plural' => 'DJs'])
        ->and($terms['customer']['singular'])->toBe('client');
});

test('running it again changes nothing', function () {
    $this->seed(IndustrySeeder::class);
    $first = tenant()->refresh()->only(['industry', 'industry_overrides']);

    $this->seed(IndustrySeeder::class);

    expect(tenant()->refresh()->only(['industry', 'industry_overrides']))->toEqual($first);
});

test('a tenant already in an industry keeps it and its own words', function () {
    $overrides = ['terms' => ['customer' => ['singular' => 'diner', 'plural' => 'diners']]];
    tenant()->update(['industry' => 'hospitality', 'industry_overrides' => $overrides]);

    $this->seed(IndustrySeeder::class);

    $tenant = tenant()->refresh();

    expect($tenant->industry)->toBe('hospitality')
        ->and($tenant->industry_overrides)->toEqual($overrides);
});
