<?php

use App\Enums\Dj\ImportStatus;
use App\Enums\Dj\LibrarySource;
use App\Enums\TenantModule;
use App\Jobs\Dj\ImportLibrary;
use App\Models\Dj\Library;
use App\Models\Dj\LibraryImport;
use App\Models\Dj\LibraryTrack;
use App\Services\Authorisation\Enums\DjPermission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Traits\BuildsRekordboxDatabasesTrait;

uses(BuildsRekordboxDatabasesTrait::class);

beforeEach(function () {
    Storage::fake(config('dj.imports.disk'));
    Queue::fake();

    $this->actingAs($this->createUserWithPermissions(DjPermission::cases()));
});

function masterDbUpload(mixed $test, string $name = 'master.db'): UploadedFile
{
    return new UploadedFile($test->makeRekordboxDatabase(), $name, null, null, true);
}

test('the library page lists libraries with their counts, and the import history', function () {
    $library = Library::factory()->source(LibrarySource::RekordboxDb)->create(['name' => 'Home']);
    LibraryTrack::factory()->for($library)->count(2)->create();
    LibraryTrack::factory()->for($library)->create(['missing_since' => now()]);
    LibraryImport::factory()->for($library)->completed()->create();

    $this->get(route('admin.dj.libraries.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dj/library/Index')
            ->has('libraries.data', 1)
            ->where('libraries.data.0.name', 'Home')
            ->where('libraries.data.0.tracks_count', 2)
            ->where('libraries.data.0.missing_tracks_count', 1)
            ->where('libraries.data.0.can_import', true)
            ->has('imports.data', 1)
            ->where('imports.data.0.status', ImportStatus::Completed->value)
            ->where('hasRunningImports', false)
            ->where('canImport', true)
        );
});

test('the page keeps checking while an import is running', function () {
    LibraryImport::factory()->create(['status' => ImportStatus::Processing]);

    $this->get(route('admin.dj.libraries.index'))
        ->assertInertia(fn (Assert $page) => $page->where('hasRunningImports', true));
});

test('uploading a master.db into a new library stores it and queues the import', function () {
    $this->post(route('admin.dj.imports.store'), [
        'source' => LibrarySource::RekordboxDb->value,
        'library_name' => 'Home',
        'file' => masterDbUpload($this),
    ])
        ->assertRedirect(route('admin.dj.libraries.index'))
        ->assertSessionHas('success', __('dj.imports.flash.started'));

    $library = Library::sole();
    $import = LibraryImport::sole();

    expect($library->name)->toBe('Home')
        ->and($library->source)->toBe(LibrarySource::RekordboxDb)
        ->and($import->library->is($library))->toBeTrue()
        ->and($import->status)->toBe(ImportStatus::Pending)
        ->and($import->original_filename)->toBe('master.db')
        ->and($import->user_id)->toBe(auth()->id());

    Storage::disk(config('dj.imports.disk'))->assertExists($import->file_path);
    Queue::assertPushed(ImportLibrary::class, fn (ImportLibrary $job) => $job->import->is($import));
});

test('a master.db can be uploaded into an existing library', function () {
    $library = Library::factory()->source(LibrarySource::RekordboxDb)->create();

    $this->post(route('admin.dj.imports.store'), [
        'source' => LibrarySource::RekordboxDb->value,
        'library_id' => $library->id,
        'file' => masterDbUpload($this),
    ])->assertSessionHasNoErrors();

    expect(Library::count())->toBe(1)
        ->and(LibraryImport::sole()->library->is($library))->toBeTrue();
});

test('uploads are validated', function (Closure $data, string $error) {
    $this->post(route('admin.dj.imports.store'), ['source' => LibrarySource::RekordboxDb->value, ...$data($this)])->assertSessionHasErrors($error);

    expect(LibraryImport::count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'no file' => [fn () => ['library_name' => 'Home'], 'file'],
    'no library or name' => [fn ($test) => ['file' => masterDbUpload($test)], 'library_name'],
    'not a .db file' => [fn ($test) => ['library_name' => 'Home', 'file' => masterDbUpload($test, 'library.xml')], 'file'],
    'an encrypted database' => [fn () => ['library_name' => 'Home', 'file' => UploadedFile::fake()->createWithContent('master.db', random_bytes(2048))], 'file'],
    'a library of another source' => [fn ($test) => ['library_id' => Library::factory()->source(LibrarySource::RekordboxXml)->create()->id, 'file' => masterDbUpload($test)], 'library_id'],
    'a file over the size limit' => [function ($test) {
        config(['dj.imports.max_size_kb' => 1]);

        return ['library_name' => 'Home', 'file' => UploadedFile::fake()->create('master.db', 2)];
    }, 'file'],
]);

test('an encrypted database is explained', function () {
    $this->post(route('admin.dj.imports.store'), [
        'source' => LibrarySource::RekordboxDb->value,
        'library_name' => 'Home',
        'file' => UploadedFile::fake()->createWithContent('master.db', random_bytes(2048)),
    ])->assertSessionHasErrors(['file' => __('dj.imports.errors.not_decrypted')]);
});

test('a library can be deleted', function () {
    $library = Library::factory()->create();

    $this->delete(route('admin.dj.libraries.destroy', $library))
        ->assertRedirect(route('admin.dj.libraries.index'))
        ->assertSessionHas('success', __('dj.libraries.flash.deleted'));

    expect($library->fresh())->toBeNull();
});

test('users who can only view the library cannot import or delete', function () {
    $this->actingAs($this->createUserWithPermissions([DjPermission::ViewLibrary]));
    $library = Library::factory()->source(LibrarySource::RekordboxDb)->create();

    $this->get(route('admin.dj.libraries.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canImport', false)
            ->where('libraries.data.0.can_import', false)
        );

    $this->post(route('admin.dj.imports.store'), ['source' => LibrarySource::RekordboxDb->value, 'library_name' => 'Home', 'file' => masterDbUpload($this)])->assertForbidden();
    $this->post(route('admin.dj.imports.store'), ['source' => LibrarySource::RekordboxDb->value, 'library_id' => $library->id, 'file' => masterDbUpload($this)])->assertForbidden();
    $this->delete(route('admin.dj.libraries.destroy', $library))->assertForbidden();

    expect($library->fresh())->not->toBeNull();
    Queue::assertNothingPushed();
});

test('users without library permissions cannot see the library', function () {
    $this->actingAs($this->createUserWithPermissions([]));

    $this->get(route('admin.dj.libraries.index'))->assertForbidden();
});

test('the library is not found when the tenant does not have the DJ module', function () {
    tenant()->update(['modules' => [TenantModule::Cms]]);

    $this->get(route('admin.dj.libraries.index'))->assertNotFound();
    $this->post(route('admin.dj.imports.store'), ['source' => LibrarySource::RekordboxDb->value, 'library_name' => 'Home', 'file' => masterDbUpload($this)])->assertNotFound();
});

function rekordboxXmlUpload(mixed $test, string $name = 'rekordbox.xml', string $product = 'rekordbox'): UploadedFile
{
    return new UploadedFile($test->makeRekordboxXml($test->sampleRekordboxXmlTracks(), $product), $name, null, null, true);
}

test('uploading a Rekordbox XML export into a new library queues its import', function () {
    $this->post(route('admin.dj.imports.store'), [
        'source' => LibrarySource::RekordboxXml->value,
        'library_name' => 'Laptop',
        'file' => rekordboxXmlUpload($this),
    ])->assertSessionHasNoErrors();

    $import = LibraryImport::sole();

    expect($import->source)->toBe(LibrarySource::RekordboxXml)
        ->and($import->library->source)->toBe(LibrarySource::RekordboxXml)
        ->and($import->original_filename)->toBe('rekordbox.xml');

    Queue::assertPushed(ImportLibrary::class);
});

test('the import page offers both upload sources', function () {
    $this->get(route('admin.dj.libraries.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('importSources.0.value', LibrarySource::RekordboxDb->value)
            ->where('importSources.1.value', LibrarySource::RekordboxXml->value)
        );
});

test('XML uploads are validated', function (Closure $data, string $error, ?string $message = null) {
    $this->post(route('admin.dj.imports.store'), $data($this))
        ->assertSessionHasErrors($message === null ? $error : [$error => __($message)]);

    expect(LibraryImport::count())->toBe(0);
})->with([
    'no source' => [fn ($test) => ['library_name' => 'Home', 'file' => rekordboxXmlUpload($test)], 'source', null],
    'a source that cannot be uploaded' => [fn ($test) => ['source' => LibrarySource::CliSync->value, 'library_name' => 'Home', 'file' => rekordboxXmlUpload($test)], 'source', null],
    'a master.db sent as XML' => [fn ($test) => ['source' => LibrarySource::RekordboxXml->value, 'library_name' => 'Home', 'file' => masterDbUpload($test)], 'file', 'dj.imports.errors.not_xml'],
    'XML from another app' => [fn ($test) => ['source' => LibrarySource::RekordboxXml->value, 'library_name' => 'Home', 'file' => rekordboxXmlUpload($test, product: 'Serato')], 'file', 'dj.imports.errors.not_rekordbox_xml'],
    'XML into a master.db library' => [fn ($test) => [
        'source' => LibrarySource::RekordboxXml->value,
        'library_id' => Library::factory()->source(LibrarySource::RekordboxDb)->create()->id,
        'file' => rekordboxXmlUpload($test),
    ], 'library_id', null],
]);
