<?php

use App\Http\Controllers\Central\MarketingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central Web Routes
|--------------------------------------------------------------------------
|
| Here you can register the central web routes for your application.
|
*/

require base_path('routes/central/fortify.php');

// The public site (lang/en/marketing.php)
Route::get('/', [MarketingController::class, 'home'])->name('home');
Route::get('modules/{module}', [MarketingController::class, 'module'])
    ->whereIn('module', MarketingController::MODULES)
    ->name('modules.show');
