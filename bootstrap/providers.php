<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\TenancyServiceProvider;
use App\Services\Navigation\NavigationServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    FortifyServiceProvider::class,
    NavigationServiceProvider::class,
];
