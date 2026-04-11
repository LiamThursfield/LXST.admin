<?php

namespace App\Services\Navigation\Exceptions;

use App\Services\Navigation\Data\MenuItem;

class DuplicateMenuItemKeyException extends DuplicateKeyException
{
    public function __construct(MenuItem $menuItem)
    {
        parent::__construct($menuItem->key, 'MenuItem');
    }
}
