<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Admin\Bookings\BookingAvailabilityController;
use App\Http\Controllers\Tenant\Admin\Bookings\BookingCalendarController;
use App\Http\Controllers\Tenant\Admin\Bookings\BookingCardController;
use App\Http\Controllers\Tenant\Admin\Bookings\BookingController;
use App\Http\Controllers\Tenant\Admin\Bookings\BookingGiftCardController;
use App\Http\Controllers\Tenant\Admin\Bookings\BookingPaymentController;
use App\Http\Controllers\Tenant\Admin\Bookings\GiftCardController;
use App\Http\Controllers\Tenant\Admin\Bookings\InvoiceController;
use App\Http\Controllers\Tenant\Admin\Bookings\MessageLogController;
use App\Http\Controllers\Tenant\Admin\Bookings\MessageTemplateController;
use App\Http\Controllers\Tenant\Admin\Bookings\PaymentRefundController;
use App\Http\Controllers\Tenant\Admin\Bookings\PaymentReportController;
use App\Http\Controllers\Tenant\Admin\Bookings\QuoteController;
use App\Http\Controllers\Tenant\Admin\Bookings\ResourceController;
use App\Http\Controllers\Tenant\Admin\Bookings\RotaController;
use App\Http\Controllers\Tenant\Admin\Bookings\ServiceCategoryController;
use App\Http\Controllers\Tenant\Admin\Bookings\ServiceController;
use App\Http\Controllers\Tenant\Admin\Bookings\ServiceSessionController;
use App\Http\Controllers\Tenant\Admin\Bookings\StaffController;
use App\Http\Controllers\Tenant\Admin\Bookings\StaffHoursController;
use App\Http\Controllers\Tenant\Admin\Bookings\TimeOffController;
use App\Http\Controllers\Tenant\Admin\Bookings\WaitlistController;
use App\Http\Controllers\Tenant\Admin\Catalogue\CatalogueController;
use App\Http\Controllers\Tenant\Admin\Catalogue\ItemStockController;
use App\Http\Controllers\Tenant\Admin\Catalogue\TagController as CatalogueTagController;
use App\Http\Controllers\Tenant\Admin\Catalogue\TagPresetController as CatalogueTagPresetController;
use App\Http\Controllers\Tenant\Admin\Cms\ContentImportController;
use App\Http\Controllers\Tenant\Admin\Cms\LayoutController;
use App\Http\Controllers\Tenant\Admin\Cms\MenuController;
use App\Http\Controllers\Tenant\Admin\Cms\PageController;
use App\Http\Controllers\Tenant\Admin\Cms\RedirectController;
use App\Http\Controllers\Tenant\Admin\Cms\SavedComponentController;
use App\Http\Controllers\Tenant\Admin\Cms\TemplateController;
use App\Http\Controllers\Tenant\Admin\Crm\ContactController;
use App\Http\Controllers\Tenant\Admin\Crm\ContactSpamController;
use App\Http\Controllers\Tenant\Admin\Crm\FormController;
use App\Http\Controllers\Tenant\Admin\Crm\FormSubmissionController;
use App\Http\Controllers\Tenant\Admin\Crm\FormSubmissionSpamController;
use App\Http\Controllers\Tenant\Admin\Crm\OrganisationUnitController;
use App\Http\Controllers\Tenant\Admin\DashboardController;
use App\Http\Controllers\Tenant\Admin\Dj\AnnualDropController;
use App\Http\Controllers\Tenant\Admin\Dj\CanonicalArtistController;
use App\Http\Controllers\Tenant\Admin\Dj\CanonicalMergeController;
use App\Http\Controllers\Tenant\Admin\Dj\CanonicalTrackController;
use App\Http\Controllers\Tenant\Admin\Dj\DjSettingsController;
use App\Http\Controllers\Tenant\Admin\Dj\GigController;
use App\Http\Controllers\Tenant\Admin\Dj\GigPinController;
use App\Http\Controllers\Tenant\Admin\Dj\GigPlayController;
use App\Http\Controllers\Tenant\Admin\Dj\GigQrCodeController;
use App\Http\Controllers\Tenant\Admin\Dj\GigStatsController;
use App\Http\Controllers\Tenant\Admin\Dj\GuestPassController;
use App\Http\Controllers\Tenant\Admin\Dj\HistoryBulkController;
use App\Http\Controllers\Tenant\Admin\Dj\HistoryController;
use App\Http\Controllers\Tenant\Admin\Dj\LibraryArtistBulkController;
use App\Http\Controllers\Tenant\Admin\Dj\LibraryArtistController;
use App\Http\Controllers\Tenant\Admin\Dj\LibraryController;
use App\Http\Controllers\Tenant\Admin\Dj\LibraryImportController;
use App\Http\Controllers\Tenant\Admin\Dj\LibraryTrackBulkController;
use App\Http\Controllers\Tenant\Admin\Dj\LibraryTrackController;
use App\Http\Controllers\Tenant\Admin\Dj\LibraryTrackSplitController;
use App\Http\Controllers\Tenant\Admin\Dj\MatchDismissalController;
use App\Http\Controllers\Tenant\Admin\Dj\MatchingController;
use App\Http\Controllers\Tenant\Admin\Dj\RequestQueueController;
use App\Http\Controllers\Tenant\Admin\Dj\SetlistController;
use App\Http\Controllers\Tenant\Admin\Dj\SetlistItemController;
use App\Http\Controllers\Tenant\Admin\Dj\SongController;
use App\Http\Controllers\Tenant\Admin\FileManager\FileController;
use App\Http\Controllers\Tenant\Admin\FileManager\FileManagerController;
use App\Http\Controllers\Tenant\Admin\FileManager\FilePickerController;
use App\Http\Controllers\Tenant\Admin\FileManager\FolderController;
use App\Http\Controllers\Tenant\Admin\Settings\PasswordController;
use App\Http\Controllers\Tenant\Admin\Settings\ProfileController;
use App\Http\Controllers\Tenant\Admin\Settings\SecurityController;
use App\Http\Controllers\Tenant\Admin\Settings\TimezoneController;
use App\Http\Controllers\Tenant\Admin\SiteSettings\ApiTokenController;
use App\Http\Controllers\Tenant\Admin\SiteSettings\EmailPreviewController;
use App\Http\Controllers\Tenant\Admin\SiteSettings\GeneralSettingsController;
use App\Http\Controllers\Tenant\Admin\SiteSettings\IndustrySettingsController;
use App\Http\Controllers\Tenant\Admin\SiteSettings\IntegrationSettingsController;
use App\Http\Controllers\Tenant\Admin\SiteSettings\MailSettingsController;
use App\Http\Controllers\Tenant\Admin\SiteSettings\MessagingSettingsController;
use App\Http\Controllers\Tenant\Admin\SiteSettings\PaymentSettingsController;
use App\Http\Controllers\Tenant\Admin\SiteSettings\ThemeSettingsController;
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

// Changes here can show on the website, so they refresh the headless API's cached pages
Route::name('file-manager.')->prefix('file-manager')->middleware('content.changed')->group(function () {
    Route::get('/', [FileManagerController::class, 'index'])->name('index');
    Route::post('folders', [FolderController::class, 'store'])->name('folders.store');
    Route::post('files', [FileController::class, 'store'])->name('files.store');
    Route::patch('files/{file}', [FileController::class, 'update'])->name('files.update');

    // JSON endpoints for the file picker used inside other pages
    Route::get('picker', [FilePickerController::class, 'index'])->name('picker.index');
    Route::get('picker/files/{file}', [FilePickerController::class, 'show'])->name('picker.files.show');
    Route::post('picker/files', [FilePickerController::class, 'store'])->name('picker.files.store');
});

Route::name('cms.')->prefix('cms')->middleware(['tenant.module:cms', 'content.changed'])->group(function () {
    // The URL a page would have, and whether it's free, for the page editor's live check
    Route::get('pages/url-check', [PageController::class, 'urlCheck'])->name('pages.url-check');
    Route::get('pages/export', [PageController::class, 'export'])->name('pages.export');
    Route::resource('pages', PageController::class);
    Route::post('saved-components/reusable', [SavedComponentController::class, 'storeReusable'])->name('saved-components.reusable.store');
    Route::get('saved-components/export', [SavedComponentController::class, 'export'])->name('saved-components.export');
    Route::resource('saved-components', SavedComponentController::class);
    Route::get('layouts/export', [LayoutController::class, 'export'])->name('layouts.export');
    Route::resource('layouts', LayoutController::class);
    Route::get('menus/export', [MenuController::class, 'export'])->name('menus.export');
    Route::resource('menus', MenuController::class);
    Route::get('templates/export', [TemplateController::class, 'export'])->name('templates.export');
    Route::resource('templates', TemplateController::class);
    Route::get('import', [ContentImportController::class, 'show'])->name('import.show');
    // A dry run changes nothing, so cached pages stay as they are
    Route::post('import/preview', [ContentImportController::class, 'preview'])->name('import.preview')->withoutMiddleware('content.changed');
    Route::post('import', [ContentImportController::class, 'store'])->name('import.store');
    Route::get('redirects/export', [RedirectController::class, 'export'])->name('redirects.export');
    Route::resource('redirects', RedirectController::class);
});

Route::name('bookings.')->prefix('bookings')->middleware('tenant.module:bookings')->group(function () {
    Route::get('bookings/availability', BookingAvailabilityController::class)->name('bookings.availability');
    Route::get('calendar', BookingCalendarController::class)->name('calendar.index');
    Route::get('messages', [MessageTemplateController::class, 'index'])->name('messages.index');
    Route::get('messages/{event}/edit', [MessageTemplateController::class, 'edit'])->name('messages.edit');
    Route::put('messages/{event}', [MessageTemplateController::class, 'update'])->name('messages.update');
    Route::get('message-log', MessageLogController::class)->name('message-log.index');
    Route::put('bookings/{booking}/move', [BookingController::class, 'move'])->name('bookings.move');
    Route::post('bookings/{booking}/message', [BookingController::class, 'message'])->middleware('throttle:20,1')->name('bookings.message');
    Route::post('bookings/{booking}/payment-requests', [BookingPaymentController::class, 'store'])->middleware('throttle:20,1')->name('bookings.payment-requests.store');
    Route::post('bookings/{booking}/payments', [BookingPaymentController::class, 'record'])->name('bookings.payments.store');
    Route::post('bookings/{booking}/card-requests', [BookingCardController::class, 'store'])->middleware('throttle:20,1')->name('bookings.card-requests.store');
    Route::post('bookings/{booking}/fees', [BookingCardController::class, 'charge'])->middleware('throttle:20,1')->name('bookings.fees.store');
    Route::get('payments', [BookingPaymentController::class, 'index'])->name('payments.index');
    Route::post('payments/{payment}/refunds', [PaymentRefundController::class, 'store'])->middleware('throttle:20,1')->name('payments.refunds.store');
    Route::post('bookings/{booking}/gift-card-payments', [BookingGiftCardController::class, 'store'])->middleware('throttle:20,1')->name('bookings.gift-card-payments.store');
    Route::post('gift-cards/{giftCard}/send', [GiftCardController::class, 'send'])->middleware('throttle:20,1')->name('gift-cards.send');
    Route::post('gift-cards/{giftCard}/cancel', [GiftCardController::class, 'cancel'])->name('gift-cards.cancel');
    Route::resource('gift-cards', GiftCardController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('reports/payments', PaymentReportController::class)->name('reports.payments');
    Route::get('bookings/{booking}/quotes/create', [QuoteController::class, 'create'])->name('bookings.quotes.create');
    Route::post('bookings/{booking}/quotes', [QuoteController::class, 'store'])->name('bookings.quotes.store');
    Route::post('quotes/{quote}/send', [QuoteController::class, 'send'])->middleware('throttle:20,1')->name('quotes.send');
    Route::post('quotes/{quote}/accept', [QuoteController::class, 'accept'])->name('quotes.accept');
    Route::post('quotes/{quote}/decline', [QuoteController::class, 'decline'])->name('quotes.decline');
    Route::resource('quotes', QuoteController::class)->only(['index', 'show', 'edit', 'update']);
    Route::get('bookings/{booking}/invoices/create', [InvoiceController::class, 'create'])->name('bookings.invoices.create');
    Route::post('bookings/{booking}/invoices', [InvoiceController::class, 'store'])->name('bookings.invoices.store');
    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->middleware('throttle:20,1')->name('invoices.send');
    Route::post('invoices/{invoice}/void', [InvoiceController::class, 'voidInvoice'])->name('invoices.void');
    Route::resource('invoices', InvoiceController::class)->only(['index', 'show']);
    Route::post('bookings/{booking}/status', [BookingController::class, 'status'])->name('bookings.status');
    Route::put('bookings/{booking}/schedule', [BookingController::class, 'schedule'])->name('bookings.schedule');
    Route::resource('bookings', BookingController::class)->only(['index', 'create', 'store', 'show']);
    Route::resource('sessions', ServiceSessionController::class)->except('show');
    Route::post('waitlist/{entry}/book', [WaitlistController::class, 'book'])->name('waitlist.book');
    Route::resource('waitlist', WaitlistController::class)->only(['index', 'create', 'store', 'destroy'])->parameters(['waitlist' => 'entry']);
    Route::resource('services', ServiceController::class);
    Route::resource('service-categories', ServiceCategoryController::class)->except('show');
    Route::resource('staff', StaffController::class)->parameters(['staff' => 'resource']);
    Route::resource('resources', ResourceController::class);
    Route::get('rota', [RotaController::class, 'index'])->name('rota.index');
    Route::put('rota', [RotaController::class, 'update'])->name('rota.update');
    Route::post('time-off/{time_off}/approve', [TimeOffController::class, 'approve'])->name('time-off.approve');
    Route::post('time-off/{time_off}/decline', [TimeOffController::class, 'decline'])->name('time-off.decline');
    Route::resource('time-off', TimeOffController::class)->only(['index', 'create', 'store', 'destroy'])->parameters(['time-off' => 'time_off']);
    Route::get('hours', StaffHoursController::class)->name('staff-hours.index');
});

// Menus, price lists and products. They can be shown on the website, so changes refresh its cache.
Route::name('catalogue.')->prefix('catalogue')->middleware(['tenant.module:catalogue', 'content.changed'])->group(function () {
    // Marking an item sold out or counting its stock, without changing the rest of the catalogue
    Route::patch('catalogues/{catalogue}/items/{item}/stock', [ItemStockController::class, 'update'])
        ->name('catalogues.items.stock');
    Route::resource('catalogues', CatalogueController::class);
    Route::post('tags/presets', [CatalogueTagPresetController::class, 'store'])->name('tags.presets.store');
    Route::resource('tags', CatalogueTagController::class)->except('show');
});

Route::name('crm.')->prefix('crm')->middleware(['tenant.module:crm', 'content.changed'])->group(function () {
    Route::patch('contacts/{contact}/restore', [ContactController::class, 'restore'])
        ->withTrashed()
        ->name('contacts.restore');
    Route::post('contacts/{contact}/spam', [ContactSpamController::class, 'store'])->name('contacts.spam.store');
    Route::delete('contacts/{contact}/spam', [ContactSpamController::class, 'destroy'])->name('contacts.spam.destroy');
    Route::resource('contacts', ContactController::class);
    Route::get('forms/export', [FormController::class, 'export'])->name('forms.export');
    Route::resource('forms', FormController::class);
    Route::post('form-submissions/{form_submission}/spam', [FormSubmissionSpamController::class, 'store'])->name('form-submissions.spam.store');
    Route::delete('form-submissions/{form_submission}/spam', [FormSubmissionSpamController::class, 'destroy'])->name('form-submissions.spam.destroy');
    Route::resource('form-submissions', FormSubmissionController::class)->only(['index', 'show']);

    Route::resource('organisation-units', OrganisationUnitController::class);
});

// Requests and stats are added here as they're built
Route::name('dj.')->prefix('dj')->middleware(['tenant.module:dj', 'content.changed:dj'])->group(function () {
    Route::resource('gigs', GigController::class)->except('show');
    Route::post('gigs/{gig}/pin', [GigPinController::class, 'store'])->name('gigs.pin');
    Route::delete('gigs/{gig}/pin', [GigPinController::class, 'destroy'])->name('gigs.unpin');
    Route::get('gigs/{gig}/qr-code', [GigQrCodeController::class, 'show'])->name('gigs.qr-code');
    Route::get('gigs/{gig}/stats', [GigStatsController::class, 'show'])->name('gigs.stats');
    Route::post('gigs/{gig}/plays', [GigPlayController::class, 'store'])->name('gigs.plays.store');
    Route::delete('gigs/{gig}/plays/{play}', [GigPlayController::class, 'destroy'])->name('gigs.plays.destroy');

    // Play history, linked to gigs or not
    // The request queue: during a gig, in the admin or full screen in the booth
    Route::get('requests', [RequestQueueController::class, 'index'])->name('requests.index');
    Route::get('requests/booth', [RequestQueueController::class, 'booth'])->name('requests.booth');
    Route::patch('requests/{item}/status', [RequestQueueController::class, 'updateStatus'])->name('requests.status');
    Route::patch('gigs/{gig}/requests-mode', [RequestQueueController::class, 'updateMode'])->name('gigs.requests-mode');
    Route::get('history', [HistoryController::class, 'index'])->name('history.index');
    Route::get('annual-drop', [AnnualDropController::class, 'index'])->name('annual-drop');
    Route::post('history/bulk/link', [HistoryBulkController::class, 'link'])->name('history.bulk.link');
    Route::post('history/bulk/create-gig', [HistoryBulkController::class, 'createGig'])->name('history.bulk.create-gig');
    Route::post('history/bulk/unlink', [HistoryBulkController::class, 'unlink'])->name('history.bulk.unlink');
    Route::post('history/bulk/stats', [HistoryBulkController::class, 'stats'])->name('history.bulk.stats');
    Route::get('history/{session}', [HistoryController::class, 'show'])->name('history.show');
    Route::resource('setlists', SetlistController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::post('setlists/{setlist}/duplicate', [SetlistController::class, 'duplicate'])->name('setlists.duplicate');
    Route::post('setlists/{setlist}/items', [SetlistItemController::class, 'store'])->name('setlists.items.store');
    Route::put('setlists/{setlist}/items/order', [SetlistItemController::class, 'reorder'])->name('setlists.items.reorder');
    Route::patch('setlists/{setlist}/items/{item}', [SetlistItemController::class, 'update'])->name('setlists.items.update');
    Route::delete('setlists/{setlist}/items/{item}', [SetlistItemController::class, 'destroy'])->name('setlists.items.destroy');
    // Guest passes on a setlist, or on a gig's requests (whose list is made with the first pass)
    Route::post('song-lists/{songList}/guest-passes', [GuestPassController::class, 'store'])->name('song-lists.guest-passes.store');
    Route::post('gigs/{gig}/guest-passes', [GuestPassController::class, 'storeForGig'])->name('gigs.guest-passes.store');
    Route::put('guest-passes/{guestPass}', [GuestPassController::class, 'update'])->name('guest-passes.update');
    Route::delete('guest-passes/{guestPass}', [GuestPassController::class, 'destroy'])->name('guest-passes.destroy');
    // DJ settings moved into Site settings
    Route::permanentRedirect('settings', '/admin/site-settings/dj');

    Route::resource('libraries', LibraryController::class)->only(['index', 'destroy']);
    Route::patch('libraries/{library}/hidden', [LibraryController::class, 'hide'])->name('libraries.hide');
    Route::post('imports', [LibraryImportController::class, 'store'])->name('imports.store');
    // Bulk actions on the tracks or artists selected on a page
    Route::post('tracks/bulk/assign', [LibraryTrackBulkController::class, 'assign'])->name('tracks.bulk.assign');
    Route::post('tracks/bulk/verify', [LibraryTrackBulkController::class, 'verify'])->name('tracks.bulk.verify');
    Route::post('tracks/bulk/hide', [LibraryTrackBulkController::class, 'hide'])->name('tracks.bulk.hide');
    Route::post('artists/bulk/assign', [LibraryArtistBulkController::class, 'assign'])->name('artists.bulk.assign');
    Route::post('artists/bulk/verify', [LibraryArtistBulkController::class, 'verify'])->name('artists.bulk.verify');
    Route::post('artists/bulk/hide', [LibraryArtistBulkController::class, 'hide'])->name('artists.bulk.hide');
    Route::resource('tracks', LibraryTrackController::class)->only(['index', 'show']);
    Route::get('artists', [LibraryArtistController::class, 'index'])->name('artists.index');

    // Canonical matching: reviewing suggestions, merging, splitting and verifying
    Route::get('matching', [MatchingController::class, 'index'])->name('matching.index');
    Route::post('matching/rematch', [MatchingController::class, 'rematch'])->name('matching.rematch');
    Route::post('matching/merges', [CanonicalMergeController::class, 'store'])->name('matching.merges.store');
    Route::post('matching/dismissals', [MatchDismissalController::class, 'store'])->name('matching.dismissals.store');
    Route::get('canonical-tracks/search', [CanonicalTrackController::class, 'search'])->name('canonical-tracks.search');
    Route::patch('canonical-tracks/{canonicalTrack}/verification', [CanonicalTrackController::class, 'verify'])->name('canonical-tracks.verify');
    Route::patch('canonical-tracks/{canonicalTrack}/hidden', [CanonicalTrackController::class, 'hide'])->name('canonical-tracks.hide');

    // Songs and artists: the canonical records, counted once across libraries
    Route::post('songs/cleanup', [SongController::class, 'cleanup'])->name('songs.cleanup');
    Route::resource('songs', SongController::class)->only(['index', 'show', 'edit', 'update']);
    Route::get('canonical-artists/search', [CanonicalArtistController::class, 'search'])->name('canonical-artists.search');
    Route::patch('canonical-artists/{canonicalArtist}/verification', [CanonicalArtistController::class, 'verify'])->name('canonical-artists.verify');
    Route::patch('canonical-artists/{canonicalArtist}/hidden', [CanonicalArtistController::class, 'hide'])->name('canonical-artists.hide');
    Route::resource('canonical-artists', CanonicalArtistController::class)
        ->only(['index', 'show', 'edit', 'update'])
        ->parameters(['canonical-artists' => 'canonicalArtist']);
    Route::post('tracks/{track}/split', [LibraryTrackSplitController::class, 'store'])->name('tracks.split');
});

Route::name('tracker.')->prefix('tracker')->middleware('tenant.module:tracker')->group(function () {
    Route::get('today', TodayController::class)->name('today');

    Route::resource('categories', CategoryController::class);
    Route::resource('tasks', TaskDefinitionController::class);
    Route::resource('holidays', HolidayController::class);

    Route::post('tasks/{task}/entries', [TaskEntryController::class, 'store'])->name('tasks.entries.store');
    Route::delete('entries/{entry}', [TaskEntryController::class, 'destroy'])->name('entries.destroy');
});

// DJ settings are a Site settings tab, for tenants with the DJ module
Route::name('site-settings.dj.')->prefix('site-settings/dj')->middleware(['tenant.module:dj', 'content.changed:dj'])->group(function () {
    Route::get('/', [DjSettingsController::class, 'edit'])->name('edit');
    Route::put('/', [DjSettingsController::class, 'update'])->name('update');
});

// API tokens don't change the site's content, so they're outside the group below, which refreshes the API's cache
Route::resource('site-settings/api-tokens', ApiTokenController::class)
    ->only(['index', 'store', 'destroy'])
    ->names('site-settings.api-tokens')
    ->parameters(['api-tokens' => 'apiToken']);

// Previews only read, so they're outside the group below, which refreshes the API's cache
// Taking payments is only for bookings, so far (#180)
Route::name('site-settings.payments.')->prefix('site-settings/payments')->middleware('tenant.module:bookings')->group(function () {
    Route::get('/', [PaymentSettingsController::class, 'edit'])->name('edit');
    Route::post('connect', [PaymentSettingsController::class, 'connect'])->name('connect');
    // Stripe sends the business here when their setup link has run out, so it can't change anything
    Route::get('refresh', [PaymentSettingsController::class, 'refresh'])->name('refresh');
    Route::get('return', [PaymentSettingsController::class, 'return'])->name('return');
    Route::delete('/', [PaymentSettingsController::class, 'disconnect'])->name('disconnect');
    Route::put('/', [PaymentSettingsController::class, 'update'])->name('update');
});

// Texts and WhatsApp are only used for bookings, so far
Route::name('site-settings.messaging.')->prefix('site-settings/messaging')->middleware('tenant.module:bookings')->group(function () {
    Route::get('/', [MessagingSettingsController::class, 'edit'])->name('edit');
    Route::put('/', [MessagingSettingsController::class, 'update'])->name('update');
    Route::post('test', [MessagingSettingsController::class, 'test'])->middleware('throttle:5,1')->name('test');
});

Route::name('site-settings.email-previews.')->prefix('site-settings/email-previews')->group(function () {
    Route::get('/', [EmailPreviewController::class, 'index'])->name('index');
    Route::get('{preview}', [EmailPreviewController::class, 'show'])->name('show');
});

Route::name('site-settings.')->prefix('site-settings')->middleware('content.changed')->group(function () {
    Route::get('general', [GeneralSettingsController::class, 'edit'])->name('general.edit');
    Route::put('general', [GeneralSettingsController::class, 'update'])->name('general.update');
    Route::get('industry', [IndustrySettingsController::class, 'edit'])->name('industry.edit');
    Route::put('industry', [IndustrySettingsController::class, 'update'])->name('industry.update');
    Route::get('integrations', [IntegrationSettingsController::class, 'edit'])->name('integrations.edit');
    Route::put('integrations', [IntegrationSettingsController::class, 'update'])->name('integrations.update');
    Route::get('theme', [ThemeSettingsController::class, 'edit'])->name('theme.edit');
    Route::put('theme', [ThemeSettingsController::class, 'update'])->name('theme.update');
    Route::get('mail', [MailSettingsController::class, 'edit'])->name('mail.edit');
    Route::put('mail', [MailSettingsController::class, 'update'])->name('mail.update');
    Route::post('mail/test', [MailSettingsController::class, 'test'])
        ->middleware('throttle:5,1')
        ->name('mail.test');
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
