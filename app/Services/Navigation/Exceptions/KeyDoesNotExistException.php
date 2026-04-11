<?php

namespace App\Services\Navigation\Exceptions;

class KeyDoesNotExistException extends NavigationServiceException
{
    public function __construct(string $key, string $type)
    {
        $message = "A $type with the key $key does not exist.";

        parent::__construct($message);
    }
}
