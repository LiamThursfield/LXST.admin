<?php

use App\Models\User;
use App\Services\Navigation\Collections\Menu;
use App\Services\Navigation\Collections\MenuSection;
use App\Services\Navigation\Exceptions\DuplicateMenuKeyException;
use App\Services\Navigation\Exceptions\MenuKeyDoesNotExistException;
use App\Services\Navigation\NavigationRegistry;
use Mockery\MockInterface;

use function Pest\Laravel\mock;

beforeEach(function () {
    $this->registry = new NavigationRegistry;
});

describe('addMenu', function () {
    it('can add a menu to the registry successfully', function () {
        $menu = new Menu;

        $result = $this->registry->addMenu($menu, 'sidebar');

        expect($result)->toBeInstanceOf(NavigationRegistry::class);
    });

    it('throws an exception when adding a menu with a duplicate key', function () {
        $this->registry->addMenu(new Menu, 'sidebar');
        $this->registry->addMenu(new Menu, 'sidebar');
    })->throws(DuplicateMenuKeyException::class);
});

describe('addSectionToMenu', function () {
    it('can add a section to an existing menu', function () {
        $menuMock = mock(Menu::class);
        $menuMock->shouldReceive('addMenuSection')->once();

        $this->registry->addMenu($menuMock, 'sidebar');
        $this->registry->addSectionToMenu(new MenuSection, 'account', 'sidebar');
    });

    it('throws an exception when adding a section to a non-existent menu', function () {
        $this->registry->addSectionToMenu(new MenuSection, 'account', 'missing-menu');
    })->throws(MenuKeyDoesNotExistException::class);
});

describe('resolveForUser', function () {
    it('can resolve a menu for a given user', function () {
        $user = User::factory()->make();
        $expectedCollection = collect(['item_1', 'item_2']);

        /** @var Menu&MockInterface $menuMock */
        $menuMock = mock(Menu::class);
        $menuMock->shouldReceive('resolveForUser')
            ->once()
            ->with($user)
            ->andReturn($expectedCollection);

        $this->registry->addMenu($menuMock, 'sidebar');

        $resolved = $this->registry->resolveForUser('sidebar', $user);

        expect($resolved)->toBe($expectedCollection);
    });
});
