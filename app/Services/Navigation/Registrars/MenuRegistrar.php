<?php

namespace App\Services\Navigation\Registrars;

use App\Services\Navigation\NavigationRegistry;

/**
 * A MenuRegistrar is used to register a Menu (along with all/some of it's sections, items, and children)
 * to the navigation registry.
 */
interface MenuRegistrar
{
    public function register(NavigationRegistry $registry): void;
}
