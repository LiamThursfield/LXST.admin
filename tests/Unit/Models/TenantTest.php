<?php

use App\Enums\TenantModule;
use App\Models\Tenant;

describe('hasModule', function () {
    it('returns true when the module is switched on', function () {
        $tenant = new Tenant(['modules' => [TenantModule::Cms, TenantModule::Tracker]]);

        expect($tenant->hasModule(TenantModule::Cms))->toBeTrue()
            ->and($tenant->hasModule(TenantModule::Tracker))->toBeTrue();
    });

    it('returns false when the module is not switched on', function () {
        $tenant = new Tenant(['modules' => [TenantModule::Cms]]);

        expect($tenant->hasModule(TenantModule::Crm))->toBeFalse();
    });

    it('returns false when the tenant has no modules set', function () {
        expect((new Tenant)->hasModule(TenantModule::Cms))->toBeFalse();
    });
});
