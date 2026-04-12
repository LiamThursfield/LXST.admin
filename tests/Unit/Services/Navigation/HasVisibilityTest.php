<?php

use App\Models\User;
use App\Services\Navigation\Data\MenuItem;

it('defaults to true when no gate is provided', function () {
    $item = new MenuItem(key: 'admin', sortOrder: 1);
    $user = User::factory()->make();

    expect($item->isVisible($user))->toBeTrue();
});

it('respects a boolean visibility explicitly set', function () {
    $item = new MenuItem(key: 'admin', sortOrder: 1);
    $item->gate(false);

    expect($item->isVisible(null))->toBeFalse();

    $item->gate(true);
    expect($item->isVisible(null))->toBeTrue();
});

it('evaluates a closure for visibility passing the user', function () {
    $item = new MenuItem(key: 'admin', sortOrder: 1);
    $user = User::factory()->make(['id' => 5]);

    $item->gate(fn ($user) => $user->id === 5);

    expect($item->isVisible($user))->toBeTrue();

    // Now test a failing closure
    $item->gate(fn ($user) => $user->id === 10);
    expect($item->isVisible($user))->toBeFalse();
});
