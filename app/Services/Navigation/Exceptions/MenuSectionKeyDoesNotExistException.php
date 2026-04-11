<?php

namespace App\Services\Navigation\Exceptions;

class MenuSectionKeyDoesNotExistException extends KeyDoesNotExistException
{
    public function __construct(string $menuItemKey)
    {
        parent::__construct($menuItemKey, 'MenuSection');
    }
}
