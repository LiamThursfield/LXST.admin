<?php

namespace App\Services\Navigation\Exceptions;

class DuplicateMenuSectionKeyException extends DuplicateKeyException
{
    public function __construct(string $menuSectionKey)
    {
        parent::__construct($menuSectionKey, 'MenuSection');
    }
}
