<?php

namespace App\Services\Navigation\Exceptions;

class DuplicateMenuKeyException extends DuplicateKeyException
{
    public function __construct(string $menuKey)
    {
        parent::__construct($menuKey, 'Menu');
    }
}
