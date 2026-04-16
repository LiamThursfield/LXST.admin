<?php

use App\Services\Authorisation\Registrars\CoreAuthorisationRegistrar;

return [
    /*
    |--------------------------------------------------------------------------
    | Autorisation Registrars
    |--------------------------------------------------------------------------
    |
    | Each registrar declares the permissions it contributes and the default
    | role assignments for those permissions. Mirrors config/navigation.php.
    |
    | Note: this is distinct from config/permission.php (Spatie's package config).
    |
    */
    'registrars' => [
        CoreAuthorisationRegistrar::class,
    ],
];
