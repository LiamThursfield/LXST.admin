<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Admin\DashboardController;
use App\Http\Controllers\Tenant\Admin\Settings\PasswordController;
use App\Http\Controllers\Tenant\Admin\Settings\ProfileController;
use App\Http\Controllers\Tenant\Admin\Settings\SecurityController;
use App\Http\Controllers\Tenant\Admin\UserController;
use App\Services\Authorisation\Enums\CorePermission;
use App\Services\Authorisation\Helpers\PermissionHelper;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant Admin Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant admin routes for your application.
|
*/

Route::get('/', DashboardController::class)->name('dashboard');

Route::resource('users', UserController::class)->middleware([
    PermissionHelper::middlewareForPermissions([
        CorePermission::ViewUsers,
        CorePermission::ManageUsers,
    ]),
]);

Route::name('settings.')->group(function () {
    Route::get('/settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('/settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');
});
