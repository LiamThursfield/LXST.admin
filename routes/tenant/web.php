<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant Web Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant web routes for your application.
|
*/

require base_path('vendor/laravel/fortify/routes/routes.php');

Route::get('/', function () {
    return redirect('login');
})->name('home');
