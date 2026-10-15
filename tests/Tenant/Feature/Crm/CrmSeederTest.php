<?php

use App\Models\Crm\Address;
use App\Models\Crm\Contact;
use App\Models\Crm\OrganisationUnit;
use Database\Seeders\CrmSeeder;

it('seeds organisation units and contacts, and is safe to re-run', function () {
    $this->seed(CrmSeeder::class);
    $this->seed(CrmSeeder::class);

    expect(OrganisationUnit::query()->count())->toBe(7)
        ->and(Contact::query()->count())->toBe(12)
        ->and(Contact::query()->where('is_spam', true)->count())->toBe(1)
        ->and(Address::query()->count())->toBe(3 + 8);
});
