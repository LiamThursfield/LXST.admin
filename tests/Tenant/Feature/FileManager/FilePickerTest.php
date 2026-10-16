<?php

use App\Models\FileManager\File;
use App\Models\FileManager\Folder;
use App\Services\Authorisation\Enums\FileManagerPermission;
use App\Services\FileManager\FileManagerStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('file_manager');
});

describe('browsing', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions([FileManagerPermission::ViewFiles]));
    });

    test('the top level lists its folders and files, and whether the user can upload', function () {
        Folder::factory()->create(['name' => 'images', 'path' => 'images']);
        File::factory()->create(['name' => 'logo.png', 'path' => 'logo.png']);

        $this->getJson(route('admin.file-manager.picker.index'))
            ->assertOk()
            ->assertJsonPath('currentFolder', null)
            ->assertJsonPath('breadcrumbs', [])
            ->assertJsonPath('subfolders.0.name', 'images')
            ->assertJsonPath('files.0.name', 'logo.png')
            ->assertJsonPath('canUpload', false);
    });

    test('a folder lists only its own contents, with breadcrumbs back to the top', function () {
        $parent = Folder::factory()->create(['name' => 'images', 'path' => 'images']);
        $folder = Folder::factory()->create(['parent_id' => $parent->id, 'name' => 'team', 'path' => 'images/team']);
        File::factory()->in($folder)->create(['name' => 'jo.jpg']);
        File::factory()->create(['name' => 'elsewhere.jpg', 'path' => 'elsewhere.jpg']);

        $this->getJson(route('admin.file-manager.picker.index', ['folder' => $folder->id]))
            ->assertOk()
            ->assertJsonPath('currentFolder', ['id' => $folder->id, 'name' => 'team'])
            ->assertJsonPath('breadcrumbs', [['id' => $parent->id, 'name' => 'images'], ['id' => $folder->id, 'name' => 'team']])
            ->assertJsonCount(1, 'files')
            ->assertJsonPath('files.0.name', 'jo.jpg');
    });

    test('only images are listed when choosing an image', function () {
        File::factory()->create(['name' => 'logo.png', 'path' => 'logo.png', 'mime_type' => 'image/png']);
        File::factory()->create(['name' => 'prices.pdf', 'path' => 'prices.pdf', 'mime_type' => 'application/pdf']);

        $this->getJson(route('admin.file-manager.picker.index', ['images_only' => 1]))
            ->assertJsonCount(1, 'files')
            ->assertJsonPath('files.0.name', 'logo.png');
    });

    test('a folder that doesn\'t exist is rejected', function () {
        $this->getJson(route('admin.file-manager.picker.index', ['folder' => 999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('folder');
    });

    test('a chosen file\'s details can be loaded, and a deleted one is not found', function () {
        $file = File::factory()->create(['name' => 'logo.png', 'path' => 'logo.png']);

        $this->getJson(route('admin.file-manager.picker.files.show', $file))
            ->assertOk()
            ->assertJsonPath('data.id', $file->id)
            ->assertJsonPath('data.url', app(FileManagerStorage::class)->url($file));

        $this->getJson(route('admin.file-manager.picker.files.show', 999))->assertNotFound();
    });
});

describe('uploading', function () {
    test('uploaded files are returned so one can be chosen straight away', function () {
        $this->actingAs($this->createUserWithPermissions(FileManagerPermission::cases()));
        $folder = Folder::factory()->create(['name' => 'images', 'path' => 'images']);

        $this->postJson(route('admin.file-manager.picker.files.store'), [
            'folder_id' => $folder->id,
            'files' => [UploadedFile::fake()->image('Team Photo.jpg')],
        ])
            ->assertCreated()
            ->assertJsonPath('files.0.name', 'team-photo.jpg')
            ->assertJsonPath('files.0.folder_id', $folder->id);

        Storage::disk('file_manager')->assertExists('images/team-photo.jpg');
    });

    test('validation errors are returned as JSON', function () {
        $this->actingAs($this->createUserWithPermissions(FileManagerPermission::cases()));

        $this->postJson(route('admin.file-manager.picker.files.store'), ['files' => [UploadedFile::fake()->create('setup.exe', 10)]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('files.0');
    });

    test('users who can only view files cannot upload', function () {
        $this->actingAs($this->createUserWithPermissions([FileManagerPermission::ViewFiles]));

        $this->postJson(route('admin.file-manager.picker.files.store'), ['files' => [UploadedFile::fake()->image('a.jpg')]])
            ->assertForbidden();
    });
});

test('users without File Manager access cannot use the picker', function () {
    $this->actingAs($this->createUserWithPermissions([]));
    $file = File::factory()->create();

    $this->getJson(route('admin.file-manager.picker.index'))->assertForbidden();
    $this->getJson(route('admin.file-manager.picker.files.show', $file))->assertForbidden();
});

test('a file id is turned into its URL, or null when there is no such file', function () {
    $file = File::factory()->create(['name' => 'logo.png', 'path' => 'logo.png']);
    $storage = app(FileManagerStorage::class);

    expect($storage->urlFor($file->id))->toBe($storage->url($file))
        ->and($storage->urlFor(999))->toBeNull()
        ->and($storage->urlFor(null))->toBeNull();
});
