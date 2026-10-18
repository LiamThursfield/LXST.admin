<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Api\V1\Cms\MenuController;
use App\Http\Controllers\Tenant\Api\V1\Cms\PageController;
use App\Http\Controllers\Tenant\Api\V1\Cms\PageViewController;
use App\Http\Controllers\Tenant\Api\V1\Crm\FormController;
use App\Http\Controllers\Tenant\Api\V1\Crm\FormSubmissionController;
use App\Http\Controllers\Tenant\Api\V1\Dj\GuestSetlistController;
use App\Http\Controllers\Tenant\Api\V1\Dj\GuestSetlistItemController;
use App\Http\Controllers\Tenant\Api\V1\Dj\GuestTokenController;
use App\Http\Controllers\Tenant\Api\V1\PingController;
use App\Http\Controllers\Tenant\Api\V1\SiteController;
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
        // Site-wide data for every page: name, branding, theme, GTM and menus
        Route::get('site', SiteController::class)->name('site');

        Route::prefix('cms')->name('cms.')->middleware('tenant.module:cms')->group(function () {
            // The page (or redirect) for a URL on the site: /cms/pages?url=/about/team
            Route::get('pages', [PageController::class, 'show'])->name('pages.show');
            Route::get('menus/{menu:slug}', [MenuController::class, 'show'])->name('menus.show');
            // Records a visit (queued), sent by the site with the visitor's details
            Route::post('page-views', [PageViewController::class, 'store'])
                ->middleware('throttle:page-views')
                ->name('page-views.store');
        });

        Route::prefix('crm')->name('crm.')->middleware('tenant.module:crm')->group(function () {
            // Forms are addressed by their slug
            Route::get('forms/{form:slug}', [FormController::class, 'show'])->name('forms.show');
            Route::post('forms/{form:slug}/submissions', [FormSubmissionController::class, 'store'])
                ->middleware('throttle:form-submissions')
                ->name('forms.submissions.store');
        });

        // Request context, song requests and now playing are added here as they're built
        Route::prefix('dj')->name('dj.')->middleware('tenant.module:dj')->group(function () {
            // A guest pass's password, exchanged for a short-lived token sent as X-Guest-Token
            Route::post('song-lists/{songList}/guest-token', [GuestTokenController::class, 'store'])
                ->middleware('throttle:dj-guest-token')
                ->name('song-lists.guest-token.store');

            // A setlist for guests with a pass: view it, and add, change or remove their songs
            Route::middleware('dj.guest-pass')->prefix('setlists/{songList}')->name('setlists.')->group(function () {
                Route::get('/', [GuestSetlistController::class, 'show'])->name('show');
                Route::get('songs', [GuestSetlistController::class, 'songs'])->name('songs');
                Route::post('items', [GuestSetlistItemController::class, 'store'])->name('items.store');
                Route::patch('items/{item}', [GuestSetlistItemController::class, 'update'])->name('items.update');
                Route::delete('items/{item}', [GuestSetlistItemController::class, 'destroy'])->name('items.destroy');
            });
        });
    });
