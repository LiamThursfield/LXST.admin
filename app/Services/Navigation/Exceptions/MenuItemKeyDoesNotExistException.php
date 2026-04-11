<?php

namespace App\Services\Navigation\Exceptions;

class MenuItemKeyDoesNotExistException extends KeyDoesNotExistException
{
    public function __construct(string $menuItemKey)
    {
        parent::__construct($menuItemKey, 'MenuItem');
    }
}
