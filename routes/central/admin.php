<?php

use App\Http\Controllers\Central\Admin\DashboardController;
use App\Http\Controllers\Central\Admin\Settings\PasswordController;
use App\Http\Controllers\Central\Admin\Settings\ProfileController;
use App\Http\Controllers\Central\Admin\Settings\SecurityController;
use App\Http\Controllers\Central\Admin\TenantController;
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

Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
Route::get('tenants/create', [TenantController::class, 'create'])->name('tenants.create');
Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
Route::get('tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
Route::get('tenants/{tenant}/edit', [TenantController::class, 'edit'])->name('tenants.edit');
Route::put('tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
Route::post('tenants/{tenant}/disable', [TenantController::class, 'disable'])->name('tenants.disable');
Route::post('tenants/{tenant}/enable', [TenantController::class, 'enable'])->name('tenants.enable');
Route::delete('tenants/{tenant}', [TenantController::class, 'destroy'])->name('tenants.destroy');

Route::name('settings.')->group(function () {
    Route::get('/settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('/settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');
});
