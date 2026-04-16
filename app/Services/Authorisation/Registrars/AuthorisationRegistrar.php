<?php

namespace App\Services\Authorisation\Registrars;

use App\Services\Authorisation\AuthorisationRegistry;

/**
 * A AuthorisationRegistrar is used to register permissions and their default
 * role assignments to the permission registry.
 */
interface AuthorisationRegistrar
{
    public function register(AuthorisationRegistry $registry): void;
}
