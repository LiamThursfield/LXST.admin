<?php

use App\Enums\TenantModule;

test('a disabled tenant\'s site, admin and API aren\'t available', function () {
    $this->actingAs($this->createUserWithPermissions([]));
    tenant()->update(['disabled_at' => now(), 'modules' => [TenantModule::Cms]]);

    $this->get(route('admin.dashboard'))->assertServiceUnavailable();
    $this->get(route('login'))->assertServiceUnavailable();
    $this->getJson(route('api.v1.ping'))
        ->assertServiceUnavailable()
        ->assertJsonPath('message', __('tenants.disabled_message'));
});

test('an enabled tenant answers as normal', function () {
    $this->actingAs($this->createUserWithPermissions([]));

    $this->get(route('admin.dashboard'))->assertOk();
    $this->getJson(route('api.v1.ping'))->assertOk();
});
