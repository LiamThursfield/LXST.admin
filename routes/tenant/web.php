<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware;

/*
|--------------------------------------------------------------------------
| Tenant Web Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant web routes for your application.
|
*/

Route::middleware([
    Middleware\ScopeSessions::class,
])->group(function () {
    Route::get('/', function () {
        return \Inertia\Inertia::render('Welcome');
    });
});
