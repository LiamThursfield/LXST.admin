<?php

use App\Services\Authorisation\Registrars\CmsAuthorisationRegistrar;
use App\Services\Authorisation\Registrars\CoreAuthorisationRegistrar;
use App\Services\Authorisation\Registrars\CrmAuthorisationRegistrar;
use App\Services\Authorisation\Registrars\DjAuthorisationRegistrar;
use App\Services\Authorisation\Registrars\FileManagerAuthorisationRegistrar;
use App\Services\Authorisation\Registrars\SettingsAuthorisationRegistrar;
use App\Services\Authorisation\Registrars\TrackerAuthorisationRegistrar;

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
        CmsAuthorisationRegistrar::class,
        CrmAuthorisationRegistrar::class,
        DjAuthorisationRegistrar::class,
        FileManagerAuthorisationRegistrar::class,
        SettingsAuthorisationRegistrar::class,
        TrackerAuthorisationRegistrar::class,
    ],
];
