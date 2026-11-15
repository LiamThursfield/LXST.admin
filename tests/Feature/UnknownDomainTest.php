<?php

use App\Models\Tenant;

test('a domain no tenant has is not found', function () {
    $this->get('http://nobody.example.test/')->assertNotFound();
    $this->get('http://nobody.example.test/admin')->assertNotFound();
});

test('the api on a domain no tenant has answers not found as json', function () {
    $this->get('http://nobody.example.test/api/v1')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});

test('a tenant\'s own domain still reaches the tenant', function () {
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => 'acme.example.test']);

    $this->get('http://acme.example.test/api/v1')->assertOk();
});
