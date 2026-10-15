<?php

use App\Models\Crm\Address;
use App\Models\Crm\OrganisationUnit;
use Database\Seeders\CrmSeeder;

it('seeds a company with locations and departments, and is safe to re-run', function () {
    $this->seed(CrmSeeder::class);
    $this->seed(CrmSeeder::class);

    expect(OrganisationUnit::query()->count())->toBe(7)
        ->and(Address::query()->count())->toBe(3);
});
