<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the locale and translations are shared with every page', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('locale', 'en')
        ->where('translations.common.actions.save', 'Save')
    );
});

test('menu labels are translated rather than shown as translation keys', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('menus.main.0.0.label', 'Dashboard')
            ->where('menus.main', fn ($menu) => ! str_contains(json_encode($menu), '"navigation.'))
        );
});
