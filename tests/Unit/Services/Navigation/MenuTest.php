<?php

use App\Models\User;
use App\Services\Navigation\Collections\Menu;
use App\Services\Navigation\Collections\MenuSection;
use App\Services\Navigation\Exceptions\DuplicateMenuSectionKeyException;
use Mockery\MockInterface;

use function Pest\Laravel\mock;

beforeEach(function () {
    $this->menu = new Menu;
});

describe('addMenuSection', function () {
    it('can add a menu section', function () {
        $section = new MenuSection;
        $this->menu->addMenuSection($section, 'settings');

        expect(invade($this->menu)->menuSections->has('settings'))->toBeTrue();
    });

    it('throws an exception for duplicate menu sections unless replace is allowed', function () {
        $this->menu->addMenuSection(new MenuSection, 'settings');

        // This should throw
        $this->menu->addMenuSection(new MenuSection, 'settings');
    })->throws(DuplicateMenuSectionKeyException::class);
});

describe('resolveForUser', function () {
    it('resolves the menu sections for the user', function () {
        $user = User::factory()->make();

        /** @var MenuSection&MockInterface $sectionMock */
        $sectionMock = mock(MenuSection::class);
        $sectionMock->shouldReceive('resolveForUser')
            ->with($user)
            ->andReturn(collect(['item1']));

        $this->menu->addMenuSection($sectionMock, 'section');

        $resolved = $this->menu->resolveForUser($user);

        expect($resolved)->toHaveCount(1)
            ->and($resolved->first())->toEqual(collect(['item1']));
    });

    it('sorts the menu sections by their sortOrder', function () {
        $user = User::factory()->make();

        $resolvedSection1 = ['sectionOneItem1'];
        /** @var MenuSection&MockInterface $sectionMock1 */
        $sectionMock1 = mock(MenuSection::class);
        $sectionMock1->shouldReceive('resolveForUser')
            ->with($user)
            ->andReturn(collect($resolvedSection1));
        invade($sectionMock1)->sortOrder = 1;

        $resolvedSection2 = ['sectionTwoItem1', 'sectionTwoItem2'];
        /** @var MenuSection&MockInterface $sectionMock2 */
        $sectionMock2 = mock(MenuSection::class);
        $sectionMock2->shouldReceive('resolveForUser')
            ->with($user)
            ->andReturn(collect($resolvedSection2));
        invade($sectionMock2)->sortOrder = 2;

        $resolvedSection3 = ['sectionThreeItem1', 'sectionThreeItem2', 'sectionThreeItem3'];
        /** @var MenuSection&MockInterface $sectionMock3 */
        $sectionMock3 = mock(MenuSection::class);
        $sectionMock3->shouldReceive('resolveForUser')
            ->with($user)
            ->andReturn(collect($resolvedSection3));
        invade($sectionMock3)->sortOrder = 3;

        // Add them out of order
        $this->menu->addMenuSection($sectionMock3, 'section3');
        $this->menu->addMenuSection($sectionMock1, 'section1');
        $this->menu->addMenuSection($sectionMock2, 'section2');

        $resolved = $this->menu->resolveForUser($user);

        expect($resolved)->toHaveCount(3)
            ->and($resolved->first())->toEqual(collect($resolvedSection1))
            ->and($resolved->get(1))->toEqual(collect($resolvedSection2))
            ->and($resolved->last())->toEqual(collect($resolvedSection3));
    });

    it('filters empty sections', function () {
        $user = User::factory()->make();

        /** @var MenuSection&MockInterface $sectionMock1 */
        $sectionMock1 = mock(MenuSection::class);
        $sectionMock1->shouldReceive('resolveForUser')->with($user)->andReturn(collect(['item1']));

        /** @var MenuSection&MockInterface $sectionMock2 */
        $sectionMock2 = mock(MenuSection::class);
        $sectionMock2->shouldReceive('resolveForUser')->with($user)->andReturn(collect()); // Empty resolving

        $this->menu->addMenuSection($sectionMock1, 'visible-section');
        $this->menu->addMenuSection($sectionMock2, 'empty-section');

        $resolved = $this->menu->resolveForUser($user);

        // Assert that the empty section was filtered out
        expect($resolved)->toHaveCount(1)
            ->and($resolved->first())->toEqual(collect(['item1']));
    });
});
