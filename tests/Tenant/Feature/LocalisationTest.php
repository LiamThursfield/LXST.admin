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
