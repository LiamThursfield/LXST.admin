<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Admin\Cms\LayoutController;
use App\Http\Controllers\Tenant\Admin\Cms\MenuController;
use App\Http\Controllers\Tenant\Admin\Cms\PageController;
use App\Http\Controllers\Tenant\Admin\Cms\RedirectController;
use App\Http\Controllers\Tenant\Admin\Cms\TemplateController;
use App\Http\Controllers\Tenant\Admin\Crm\ContactController;
use App\Http\Controllers\Tenant\Admin\Crm\ContactSpamController;
use App\Http\Controllers\Tenant\Admin\Crm\FormController;
use App\Http\Controllers\Tenant\Admin\Crm\FormSubmissionController;
use App\Http\Controllers\Tenant\Admin\Crm\FormSubmissionSpamController;
use App\Http\Controllers\Tenant\Admin\Crm\OrganisationUnitController;
use App\Http\Controllers\Tenant\Admin\DashboardController;
use App\Http\Controllers\Tenant\Admin\Dj\CanonicalArtistController;
use App\Http\Controllers\Tenant\Admin\Dj\CanonicalMergeController;
use App\Http\Controllers\Tenant\Admin\Dj\CanonicalTrackController;
use App\Http\Controllers\Tenant\Admin\Dj\DjSettingsController;
use App\Http\Controllers\Tenant\Admin\Dj\GigController;
use App\Http\Controllers\Tenant\Admin\Dj\GigPinController;
use App\Http\Controllers\Tenant\Admin\Dj\GigPlayController;
use App\Http\Controllers\Tenant\Admin\Dj\GigQrCodeController;
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
use App\Http\Controllers\Tenant\Admin\SiteSettings\IntegrationSettingsController;
use App\Http\Controllers\Tenant\Admin\SiteSettings\MailSettingsController;
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
    Route::resource('pages', PageController::class);
    Route::resource('layouts', LayoutController::class);
    Route::resource('menus', MenuController::class);
    Route::resource('templates', TemplateController::class);
    Route::resource('redirects', RedirectController::class)->except('show');
});

Route::name('crm.')->prefix('crm')->middleware(['tenant.module:crm', 'content.changed'])->group(function () {
    Route::patch('contacts/{contact}/restore', [ContactController::class, 'restore'])
        ->withTrashed()
        ->name('contacts.restore');
    Route::post('contacts/{contact}/spam', [ContactSpamController::class, 'store'])->name('contacts.spam.store');
    Route::delete('contacts/{contact}/spam', [ContactSpamController::class, 'destroy'])->name('contacts.spam.destroy');
    Route::resource('contacts', ContactController::class);
    Route::resource('forms', FormController::class);
    Route::post('form-submissions/{form_submission}/spam', [FormSubmissionSpamController::class, 'store'])->name('form-submissions.spam.store');
    Route::delete('form-submissions/{form_submission}/spam', [FormSubmissionSpamController::class, 'destroy'])->name('form-submissions.spam.destroy');
    Route::resource('form-submissions', FormSubmissionController::class)->only(['index', 'show']);

    Route::resource('organisation-units', OrganisationUnitController::class);
});

// Requests and stats are added here as they're built
Route::name('dj.')->prefix('dj')->middleware('tenant.module:dj')->group(function () {
    Route::resource('gigs', GigController::class)->except('show');
    Route::post('gigs/{gig}/pin', [GigPinController::class, 'store'])->name('gigs.pin');
    Route::delete('gigs/{gig}/pin', [GigPinController::class, 'destroy'])->name('gigs.unpin');
    Route::get('gigs/{gig}/qr-code', [GigQrCodeController::class, 'show'])->name('gigs.qr-code');
    Route::post('gigs/{gig}/plays', [GigPlayController::class, 'store'])->name('gigs.plays.store');
    Route::delete('gigs/{gig}/plays/{play}', [GigPlayController::class, 'destroy'])->name('gigs.plays.destroy');

    // Play history, linked to gigs or not
    Route::get('history', [HistoryController::class, 'index'])->name('history.index');
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
    Route::get('settings', [DjSettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [DjSettingsController::class, 'update'])->name('settings.update');

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
    Route::resource('songs', SongController::class)->only(['index', 'show', 'update']);
    Route::get('canonical-artists/search', [CanonicalArtistController::class, 'search'])->name('canonical-artists.search');
    Route::patch('canonical-artists/{canonicalArtist}/verification', [CanonicalArtistController::class, 'verify'])->name('canonical-artists.verify');
    Route::patch('canonical-artists/{canonicalArtist}/hidden', [CanonicalArtistController::class, 'hide'])->name('canonical-artists.hide');
    Route::resource('canonical-artists', CanonicalArtistController::class)
        ->only(['index', 'show', 'update'])
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

// API tokens don't change the site's content, so they're outside the group below, which refreshes the API's cache
Route::resource('site-settings/api-tokens', ApiTokenController::class)
    ->only(['index', 'store', 'destroy'])
    ->names('site-settings.api-tokens')
    ->parameters(['api-tokens' => 'apiToken']);

// Previews only read, so they're outside the group below, which refreshes the API's cache
Route::name('site-settings.email-previews.')->prefix('site-settings/email-previews')->group(function () {
    Route::get('/', [EmailPreviewController::class, 'index'])->name('index');
    Route::get('{preview}', [EmailPreviewController::class, 'show'])->name('show');
});

Route::name('site-settings.')->prefix('site-settings')->middleware('content.changed')->group(function () {
    Route::get('general', [GeneralSettingsController::class, 'edit'])->name('general.edit');
    Route::put('general', [GeneralSettingsController::class, 'update'])->name('general.update');
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
