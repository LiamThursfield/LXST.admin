<?php

use App\Models\User;
use App\Services\Navigation\Collections\MenuSection;
use App\Services\Navigation\Data\MenuChildItem;
use App\Services\Navigation\Data\MenuItem;
use App\Services\Navigation\Enums\MenuItemType;

it('filters out invisible menu items when resolving', function () {
    $section = new MenuSection;

    // Add a visible item - important that it is a link as that means it will be visible even
    // without children
    $item1 = new MenuItem(key: 'visible-item', sortOrder: 1, label: 'Visible Link', type: MenuItemType::LINK, to: '/');
    $item1->gate(true);
    $section->addMenuItem($item1);

    // Add an invisible label
    $item2 = new MenuItem(key: 'invisible-item', sortOrder: 2, label: 'Invisible Label', type: MenuItemType::LABEL);
    $item2->gate(false);
    $section->addMenuItem($item2);

    // Add an invisible item with children
    $item3 = new MenuItem(
        key: 'invisible-item-with-children',
        sortOrder: 3,
        label: 'Invisible Item with Children',
        children: collect([
            new MenuChildItem(key: 'child', sortOrder: 0, to: '/'),
        ]));
    $item3->gate(false);
    $section->addMenuItem($item3);

    $user = User::factory()->make();
    $resolved = $section->resolveForUser($user);

    // Should only contain the visible item, no nulls
    expect($resolved)->toHaveCount(1)
        ->and($resolved->first()->key)->toBe('visible-item');
});

it('resolves to nothing when only labels are visible', function () {
    $section = new MenuSection;

    $label = new MenuItem(key: 'label', sortOrder: 0, label: 'Label', type: MenuItemType::LABEL);
    $section->addMenuItem($label);

    $hiddenLink = new MenuItem(key: 'hidden-link', sortOrder: 1, label: 'Hidden Link', type: MenuItemType::LINK, to: '/');
    $hiddenLink->gate(false);
    $section->addMenuItem($hiddenLink);

    expect($section->resolveForUser(User::factory()->make()))->toBeEmpty();
});

it('keeps labels when a link is visible beside them', function () {
    $section = new MenuSection;

    $section->addMenuItem(new MenuItem(key: 'label', sortOrder: 0, label: 'Label', type: MenuItemType::LABEL));
    $section->addMenuItem(new MenuItem(key: 'link', sortOrder: 1, label: 'Link', type: MenuItemType::LINK, to: '/'));

    expect($section->resolveForUser(User::factory()->make())->pluck('key')->all())->toBe(['label', 'link']);
});
