<?php

declare(strict_types=1);

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Tenant\DashboardController;
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

Route::middleware(['auth'])
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::name('settings.')->group(function () {
            Route::get('/settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
            Route::patch('/settings/profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::delete('/settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

            Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

            Route::put('/settings/password', [PasswordController::class, 'update'])
                ->middleware('throttle:6,1')
                ->name('password.update');
        });

    });
