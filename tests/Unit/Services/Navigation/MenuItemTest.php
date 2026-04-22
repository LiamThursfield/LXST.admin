<?php

use App\Services\Navigation\Data\MenuItem;
use App\Services\Navigation\Enums\MenuItemType;
use Illuminate\Routing\Route;

describe('transform (toArray)', function () {
    it('does not transform the `key` property when it does not contain a dot', function () {
        $item = new MenuItem(
            key: 'account_settings',
            sortOrder: 1,
        );

        $transformed = $item->toArray();
        expect($transformed['key'])->toBe('account_settings');
    });

    it('correctly transforms the `key` property when it contains a dot', function () {
        $item = new MenuItem(
            key: 'account.settings',
            sortOrder: 1,
        );

        $transformed = $item->toArray();
        expect($transformed['key'])->toBe('accountsettings');
    });

    it('correctly transforms the `to` property when set to a valid route name', function () {
        $item = new MenuItem(
            key: 'key',
            sortOrder: 1,
            to: 'admin.dashboard',
        );

        $transformed = $item->toArray();
        expect($transformed['to'])->toBe(route('admin.dashboard', [], false));
    });

    it('does not transform the `to` property when it is not a valid route name', function () {
        $item = new MenuItem(
            key: 'key',
            sortOrder: 1,
            to: 'some.fake.route.name',
        );

        $transformed = $item->toArray();
        expect($transformed['to'])->toBe('some.fake.route.name');
    });

    it('does not transform the `to` property when it is not an external url', function () {
        $item = new MenuItem(
            key: 'key',
            sortOrder: 1,
            to: 'https://example.com',
        );

        $transformed = $item->toArray();
        expect($transformed['to'])->toBe('https://example.com');
    });

    it('correctly transforms the `activePatterns` property and evaluates to false when no route matches', function () {
        $item = new MenuItem(
            key: 'key',
            sortOrder: 1,
            activePatterns: ['admin.dashboard'],
        );

        $transformed = $item->toArray();
        expect($transformed['defaultOpen'])->toBeFalse();
    });

    it('correctly transforms the `activePatterns` property to `defaultOpen` and evaluates to true when a route matches', function () {
        $route = new Route('GET', 'admin/dashboard', ['as' => 'admin.dashboard']);
        $route->bind(request());
        request()->setRouteResolver(fn () => $route);

        $item = new MenuItem(
            key: 'key',
            sortOrder: 1,
            activePatterns: ['admin.dashboard'],
        );

        $transformed = $item->toArray();
        expect($transformed['defaultOpen'])->toBeTrue();
    });

    it('sorts the child items based on their sortOrder', function () {
        $item = new MenuItem(
            key: 'key',
            sortOrder: 1,
            children: collect([
                new MenuItem(key: 'child2', sortOrder: 20),
                new MenuItem(key: 'child1', sortOrder: 1),
                new MenuItem(key: 'child3', sortOrder: 300),
            ])
        );

        $transformed = $item->toArray();

        expect($transformed['children'][0]['key'])->toBe('child1')
            ->and($transformed['children'][1]['key'])->toBe('child2')
            ->and($transformed['children'][2]['key'])->toBe('child3');
    });
});

describe('isLabel', function () {
    it('returns true when the type is set to label', function () {
        $item = new MenuItem(key: 'key', sortOrder: 1, type: MenuItemType::LABEL);
        expect($item->isLabel())->toBeTrue();
    });

    it('returns false when the type is anything other than a label', function () {
        foreach ([MenuItemType::LINK, MenuItemType::TRIGGER] as $type) {
            $item = new MenuItem(key: 'key', sortOrder: 1, type: $type);
            expect($item->isLabel())->toBeFalse();
        }
    });
});

describe('hasLink', function () {
    it('returns true if the item has a link', function () {
        $item = new MenuItem(key: 'key', sortOrder: 1, to: '/dashboard');
        expect($item->hasLink())->toBeTrue();
    });

    it('returns false if the item link not set', function () {
        $item = new MenuItem(key: 'key', sortOrder: 1);
        expect($item->hasLink())->toBeFalse();
    });

    it('returns false if the item link is an empty string', function () {
        $item = new MenuItem(key: 'key', sortOrder: 1, to: '');
        expect($item->hasLink())->toBeFalse();
    });

    it('returns false if the item link null', function () {
        $item = new MenuItem(key: 'key', sortOrder: 1, to: null);
        expect($item->hasLink())->toBeFalse();
    });
});
