<?php

namespace App\Services\Navigation;

use App\Services\Navigation\Data\MenuItem;
use App\Services\Navigation\Exceptions\MenuKeyDoesNotExistException;
use App\Services\Navigation\Registrars\MenuRegistrar;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Collection;

class NavigationService
{
    protected bool $initialised = false;

    /**
     * @param  array<class-string<MenuRegistrar>>  $registrars
     */
    public function __construct(
        protected readonly NavigationRegistry $registry,
        protected readonly array $registrars
    ) {}

    /**
     * Returns the Menu Item collection for the given Menu, if it exists, via the navigation registry.
     * Sorted by sort order, and ensuring there are no sections/items visible
     * that shouldn't be.
     *
     * @return Collection<int, Collection<int, MenuItem>>
     *
     * @throws MenuKeyDoesNotExistException
     * @throws BindingResolutionException
     */
    public function resolveMenuForUser(string $menuKey, ?Authenticatable $user = null): Collection
    {
        if (! $this->initialised) {
            $this->initialise();
        }

        return $this->registry->resolveForUser($menuKey, $user);
    }

    /**
     * Initialise the navigation registry using the registrars
     *
     * @throws BindingResolutionException
     */
    protected function initialise(): void
    {
        if ($this->initialised) {
            return;
        }

        // Then load them and trigger their register method
        foreach ($this->registrars as $registrar) {
            (app()->make($registrar))->register($this->registry);
        }

        $this->initialised = true;
    }
}
