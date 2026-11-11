<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Public\Bookings\GiftCardController;
use App\Http\Controllers\Tenant\Public\Bookings\GiftCardShopController;
use App\Http\Controllers\Tenant\Public\Bookings\InvoiceController;
use App\Http\Controllers\Tenant\Public\Bookings\PaymentController;
use App\Http\Controllers\Tenant\Public\Bookings\QuoteController;
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

// Buying a gift card or voucher online, and the card's own page with its code (#180)
Route::middleware('tenant.module:bookings')->group(function () {
    Route::get('gift-cards', [GiftCardShopController::class, 'show'])->middleware('throttle:60,1')->name('bookings.gift-cards.shop');
    Route::post('gift-cards', [GiftCardShopController::class, 'store'])->middleware('throttle:10,1')->name('bookings.gift-cards.buy');
    Route::get('gift-card/{publicId}', [GiftCardController::class, 'show'])->middleware('throttle:60,1')->name('bookings.gift-cards.show');
});

// The links sent to a customer with a quote to accept, and an invoice to see and pay (#180)
Route::middleware('tenant.module:bookings')->group(function () {
    Route::get('quote/{publicId}', [QuoteController::class, 'show'])->middleware('throttle:60,1')->name('bookings.quotes.show');
    Route::post('quote/{publicId}/accept', [QuoteController::class, 'accept'])->middleware('throttle:10,1')->name('bookings.quotes.accept');
    Route::post('quote/{publicId}/decline', [QuoteController::class, 'decline'])->middleware('throttle:10,1')->name('bookings.quotes.decline');
    Route::get('invoice/{publicId}', [InvoiceController::class, 'show'])->middleware('throttle:60,1')->name('bookings.invoices.show');
    Route::post('invoice/{publicId}/pay', [InvoiceController::class, 'pay'])->middleware('throttle:10,1')->name('bookings.invoices.pay');
});
