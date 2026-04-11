<?php

namespace App\Services\Navigation\Exceptions;

class DuplicateKeyException extends NavigationServiceException
{
    public function __construct(string $key, string $type)
    {
        $message = "A $type with the key $key already exists.";

        parent::__construct($message);
    }
}
