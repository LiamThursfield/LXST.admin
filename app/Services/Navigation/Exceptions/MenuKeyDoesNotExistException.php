<?php

namespace App\Services\Navigation\Exceptions;

class MenuKeyDoesNotExistException extends KeyDoesNotExistException
{
    public function __construct(string $menuKey)
    {
        parent::__construct($menuKey, 'Menu');
    }
}
