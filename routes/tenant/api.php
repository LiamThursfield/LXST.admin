<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Api\V1\Crm\FormController;
use App\Http\Controllers\Tenant\Api\V1\Crm\FormSubmissionController;
use App\Http\Controllers\Tenant\Api\V1\PingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant API Routes
|--------------------------------------------------------------------------
|
| The public, headless API that detached tenant websites call, e.g. to fetch
| pages or submit forms. The tenant is identified by the request domain.
|
| Every route is versioned (/api/v1/...), rate limited by the `tenant-api`
| limiter, and module routes should use the `tenant.module:{module}` middleware.
|
*/

Route::prefix('v1')
    ->name('v1.')
    ->middleware('throttle:tenant-api')
    ->group(function () {
        Route::get('/', PingController::class)->name('ping');

        Route::prefix('crm')->name('crm.')->middleware('tenant.module:crm')->group(function () {
            // Forms are addressed by their slug
            Route::get('forms/{form:slug}', [FormController::class, 'show'])->name('forms.show');
            Route::post('forms/{form:slug}/submissions', [FormSubmissionController::class, 'store'])
                ->middleware('throttle:form-submissions')
                ->name('forms.submissions.store');
        });
    });
