<?php

use App\Enums\TenantModule;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

test('the ping endpoint responds with JSON', function () {
    $this->getJson(route('api.v1.ping'))
        ->assertOk()
        ->assertExactJson(['data' => ['status' => 'ok', 'version' => 'v1']]);
});

test('any origin may call the API', function () {
    $this->withHeaders(['Origin' => 'https://some-tenant-site.example'])
        ->getJson(route('api.v1.ping'))
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', '*');
});

test('preflight requests are answered with CORS headers', function () {
    $this->withHeaders([
        'Origin' => 'https://some-tenant-site.example',
        'Access-Control-Request-Method' => 'POST',
    ])->options(route('api.v1.ping'))
        ->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', '*');
});

test('unknown API routes return a JSON 404 even without a JSON accept header', function () {
    $this->get('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure(['message']);
});

test('requests over the rate limit return a JSON 429', function () {
    config(['api.rate_limit_per_minute' => 2]);

    $this->get(route('api.v1.ping'))->assertOk();
    $this->get(route('api.v1.ping'))->assertOk();

    $this->get(route('api.v1.ping'))
        ->assertTooManyRequests()
        ->assertHeader('Content-Type', 'application/json');
});

test('module routes return a JSON 404 when the tenant does not have the module', function () {
    Route::middleware(['api', InitializeTenancyByDomain::class, 'tenant.module:cms'])
        ->get('/api/v1/module-test', fn () => ['ok' => true]);

    $this->get('/api/v1/module-test')->assertOk();

    tenant()->update(['modules' => [TenantModule::Tracker]]);

    $this->get('/api/v1/module-test')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json');
});
