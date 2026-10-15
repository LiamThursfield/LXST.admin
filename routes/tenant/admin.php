<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Admin\Crm\ContactController;
use App\Http\Controllers\Tenant\Admin\Crm\ContactSpamController;
use App\Http\Controllers\Tenant\Admin\Crm\OrganisationUnitController;
use App\Http\Controllers\Tenant\Admin\DashboardController;
use App\Http\Controllers\Tenant\Admin\Settings\PasswordController;
use App\Http\Controllers\Tenant\Admin\Settings\ProfileController;
use App\Http\Controllers\Tenant\Admin\Settings\SecurityController;
use App\Http\Controllers\Tenant\Admin\Settings\TimezoneController;
use App\Http\Controllers\Tenant\Admin\Tracker\CategoryController;
use App\Http\Controllers\Tenant\Admin\Tracker\HolidayController;
use App\Http\Controllers\Tenant\Admin\Tracker\TaskDefinitionController;
use App\Http\Controllers\Tenant\Admin\Tracker\TaskEntryController;
use App\Http\Controllers\Tenant\Admin\Tracker\TodayController;
use App\Http\Controllers\Tenant\Admin\UserController;
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

Route::resource('users', UserController::class);

Route::name('crm.')->prefix('crm')->middleware('tenant.module:crm')->group(function () {
    Route::patch('contacts/{contact}/restore', [ContactController::class, 'restore'])
        ->withTrashed()
        ->name('contacts.restore');
    Route::post('contacts/{contact}/spam', [ContactSpamController::class, 'store'])->name('contacts.spam.store');
    Route::delete('contacts/{contact}/spam', [ContactSpamController::class, 'destroy'])->name('contacts.spam.destroy');
    Route::resource('contacts', ContactController::class);

    Route::resource('organisation-units', OrganisationUnitController::class);
});

Route::name('tracker.')->prefix('tracker')->middleware('tenant.module:tracker')->group(function () {
    Route::get('today', TodayController::class)->name('today');

    Route::resource('categories', CategoryController::class);
    Route::resource('tasks', TaskDefinitionController::class);
    Route::resource('holidays', HolidayController::class);

    Route::post('tasks/{task}/entries', [TaskEntryController::class, 'store'])->name('tasks.entries.store');
    Route::delete('entries/{entry}', [TaskEntryController::class, 'destroy'])->name('entries.destroy');
});

Route::name('settings.')->group(function () {
    Route::get('/settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::patch('/settings/timezone', TimezoneController::class)->name('timezone.update');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('/settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');
});
