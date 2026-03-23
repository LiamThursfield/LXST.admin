<?php

use App\Http\Controllers\Central\Web\DashboardController;
use App\Http\Controllers\Central\Web\Settings\PasswordController;
use App\Http\Controllers\Central\Web\Settings\ProfileController;
use App\Http\Controllers\Central\Web\Settings\SecurityController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Central Web Routes
|--------------------------------------------------------------------------
|
| Here you can register the central web routes for your application.
|
*/

require base_path('routes/central/fortify.php');

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => false,
    ]);
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
