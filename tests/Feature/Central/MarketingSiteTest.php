<?php

use App\Http\Controllers\Central\MarketingController;
use Inertia\Testing\AssertableInertia as Assert;

test('the home page shows to visitors', function () {
    $this->get(route('central.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('marketing/Home'));
});

test('each module has a page', function (string $module) {
    $this->get(route('central.modules.show', $module))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('marketing/Module')
            ->where('module', $module));
})->with(MarketingController::MODULES);

test('a module that does not exist is not found', function () {
    $this->get(route('central.modules.show', 'payroll'))->assertNotFound();
});

test('each module page has its copy', function (string $module) {
    $copy = __("marketing.modules.{$module}");

    expect($copy)
        ->toBeArray()
        ->toHaveKeys(['name', 'summary', 'tagline', 'intro', 'highlights', 'features', 'works_with']);

    // Every module it works with must have a page of its own
    expect(array_keys($copy['works_with']))->each->toBeIn(MarketingController::MODULES);
})->with(MarketingController::MODULES);
