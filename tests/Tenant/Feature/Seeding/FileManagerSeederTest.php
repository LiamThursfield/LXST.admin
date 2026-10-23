<?php

use App\Models\FileManager\File;
use App\Models\FileManager\Folder;
use App\Services\Authorisation\Enums\FileManagerPermission;
use Database\Seeders\FileManagerSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('file_manager');
});

test('the file manager seeder makes nested folders, with real images and PDFs on the disk', function () {
    $this->seed(FileManagerSeeder::class);

    $hero = File::query()->where('path', 'images/hero.jpg')->sole();
    $favicon = getimagesizefromstring((string) Storage::disk('file_manager')->get('branding/favicon.png'));

    expect(Folder::query()->where('path', 'images/gigs')->sole()->parent->path)->toBe('images')
        ->and(File::count())->toBe(count(FileManagerSeeder::IMAGES) + count(FileManagerSeeder::DOCUMENTS))
        ->and($hero->mime_type)->toBe('image/jpeg')
        ->and($hero->alt_text)->not->toBeNull()
        ->and(File::query()->where('mime_type', 'like', 'image/%')->value('id'))->toBe($hero->id)
        ->and([$favicon[0], $favicon[1], $favicon['mime']])->toBe([64, 64, 'image/png'])
        ->and(File::query()->where('path', 'documents/price-list-2026.pdf')->sole()->mime_type)->toBe('application/pdf')
        ->and(Storage::disk('file_manager')->get('documents/price-list-2026.pdf'))->toStartWith('%PDF-');
});

test('the file manager seeder replaces files an earlier reset left on the disk', function () {
    Storage::disk('file_manager')->put('images/hero.jpg', 'left over');

    $this->seed(FileManagerSeeder::class);

    expect(File::query()->where('path', 'images/hero.jpg')->exists())->toBeTrue()
        ->and(File::query()->where('name', 'hero-2.jpg')->exists())->toBeFalse();
});

test('the file manager and picker load with the seeded files', function () {
    $this->seed(FileManagerSeeder::class);
    $this->actingAs($this->createUserWithPermissions([FileManagerPermission::ViewFiles]));

    $this->get(route('admin.file-manager.index', ['folder' => Folder::query()->where('path', 'images')->value('id')]))->assertOk();
    $this->get(route('admin.file-manager.picker.index'))->assertOk();
});

test('running the file manager seeder again creates nothing new', function () {
    $this->seed(FileManagerSeeder::class);
    $counts = [Folder::count(), File::count()];

    $this->seed(FileManagerSeeder::class);

    expect([Folder::count(), File::count()])->toBe($counts);
});
