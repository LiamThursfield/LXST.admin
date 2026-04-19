<?php

use App\Http\Controllers\Central\Admin\DashboardController;
use App\Http\Controllers\Central\Admin\Settings\PasswordController;
use App\Http\Controllers\Central\Admin\Settings\ProfileController;
use App\Http\Controllers\Central\Admin\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central Admin Routes
|--------------------------------------------------------------------------
|
| Here you can register the central admin routes for your application.
|
*/

Route::get('/', DashboardController::class)->name('dashboard');

Route::name('settings.')->group(function () {
    Route::get('/settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('/settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');
});
