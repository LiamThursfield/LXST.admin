<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant API Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant api routes for your application.
|
*/

Route::get('/', function () {
    return 'This is your multi-tenant application. The id of the current tenant is '.tenant('id')."\n";
});
