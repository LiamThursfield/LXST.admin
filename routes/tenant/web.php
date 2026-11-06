<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Public\Bookings\PaymentController;
use App\Http\Controllers\Tenant\Public\Bookings\SavedCardController;
use App\Http\Controllers\Tenant\Public\Dj\HostedRequestController;
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

// The hosted request page and guest setlists, for DJs without their own site (#123)
Route::name('dj.')->middleware('tenant.module:dj')->group(function () {
    Route::get('request', [HostedRequestController::class, 'current'])->name('request');
    Route::get('request/{gig:public_id}', [HostedRequestController::class, 'gig'])->name('request.gig');
    Route::get('setlist/{songList:public_id}', [HostedRequestController::class, 'setlist'])->name('setlist');
});

// The link sent to a customer to pay towards a booking (#180)
Route::get('pay/{publicId}', [PaymentController::class, 'show'])
    ->middleware(['tenant.module:bookings', 'throttle:60,1'])
    ->name('bookings.payments.show');

// The link sent to a customer to save a card for later fees (#180)
Route::get('card/{publicId}', [SavedCardController::class, 'show'])
    ->middleware(['tenant.module:bookings', 'throttle:60,1'])
    ->name('bookings.cards.show');
