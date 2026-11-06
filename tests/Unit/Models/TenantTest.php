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

test('the platform fee is a percentage plus a fixed amount, never more than the payment', function () {
    expect((new Tenant)->platformFeeFor(1000))->toBe(0)
        ->and((new Tenant(['platform_fee_percent' => 2.5, 'platform_fee_fixed' => 20]))->platformFeeFor(1000))->toBe(45)
        ->and((new Tenant(['platform_fee_fixed' => 500]))->platformFeeFor(300))->toBe(300);
});
