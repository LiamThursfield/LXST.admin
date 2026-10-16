<?php

use App\Models\FileManager\File;
use App\Models\FileManager\Folder;
use App\Services\Authorisation\Enums\FileManagerPermission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('file_manager');
});

function pdf(string $name = 'brochure.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%âãÏÓ\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

describe('uploading', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(FileManagerPermission::cases()));
    });

    test('files are stored in the folder with URL-friendly names, and recorded', function () {
        $folder = Folder::factory()->create(['name' => 'images', 'path' => 'images']);

        $this->post(route('admin.file-manager.files.store'), [
            'folder_id' => $folder->id,
            'files' => [UploadedFile::fake()->image('Team Photo.JPG', 40, 30), pdf('Price List.pdf')],
        ])->assertRedirect(route('admin.file-manager.index', ['folder' => $folder->id]));

        $photo = File::query()->where('name', 'team-photo.jpg')->sole();

        expect($photo->path)->toBe('images/team-photo.jpg')
            ->and($photo->folder_id)->toBe($folder->id)
            ->and($photo->mime_type)->toBe('image/jpeg')
            ->and($photo->size)->toBeGreaterThan(0)
            ->and(File::query()->where('path', 'images/price-list.pdf')->exists())->toBeTrue();

        Storage::disk('file_manager')->assertExists(['images/team-photo.jpg', 'images/price-list.pdf']);
    });

    test('a name already used in the folder gets a numeric suffix', function () {
        File::factory()->create(['name' => 'logo.png', 'path' => 'logo.png']);

        $this->post(route('admin.file-manager.files.store'), ['files' => [UploadedFile::fake()->image('logo.png')]]);
        $this->post(route('admin.file-manager.files.store'), ['files' => [UploadedFile::fake()->image('Logo.png')]]);

        expect(File::query()->orderBy('id')->pluck('name')->all())->toBe(['logo.png', 'logo-2.png', 'logo-3.png']);
    });

    test('files of a type that isn\'t allowed are rejected, naming the file', function () {
        $this->post(route('admin.file-manager.files.store'), ['files' => [UploadedFile::fake()->create('setup.exe', 10)]])
            ->assertSessionHasErrors('files.0');

        // A disguised file: allowed extension, but the contents don't match it. A real file is
        // needed, because fake uploads report their type from the name rather than the contents.
        $path = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, '<?php echo "hi";');

        $this->post(route('admin.file-manager.files.store'), ['files' => [new UploadedFile($path, 'photo.jpg', null, null, true)]])
            ->assertSessionHasErrors('files.0');

        @unlink($path);

        expect(File::query()->count())->toBe(0);
    });

    test('files over the size limit are rejected', function () {
        config(['file-manager.max_size_kb' => 50]);

        $this->post(route('admin.file-manager.files.store'), ['files' => [UploadedFile::fake()->image('big.png')->size(100)]])
            ->assertSessionHasErrors(['files.0' => __('file_manager.files.validation.size', ['size' => 0])]);
    });

    test('at least one file is required', function () {
        $this->post(route('admin.file-manager.files.store'), ['files' => []])->assertSessionHasErrors('files');
    });
});

describe('access', function () {
    test('users who can only view files cannot upload or edit them', function () {
        $this->actingAs($this->createUserWithPermissions([FileManagerPermission::ViewFiles]));
        $file = File::factory()->create();

        $this->post(route('admin.file-manager.files.store'), ['files' => [pdf()]])->assertForbidden();
        $this->patch(route('admin.file-manager.files.update', $file), ['alt_text' => 'Nope'])->assertForbidden();
    });

    test('uploads can be switched off', function () {
        config(['file-manager.uploads_enabled' => false]);
        $this->actingAs($this->createUserWithPermissions(FileManagerPermission::cases()));

        $this->post(route('admin.file-manager.files.store'), ['files' => [pdf()]])->assertForbidden();
    });
});

test('a file\'s alt text can be edited', function () {
    $this->actingAs($this->createUserWithPermissions(FileManagerPermission::cases()));
    $file = File::factory()->create();

    $this->patch(route('admin.file-manager.files.update', $file), ['alt_text' => 'The team at the summer party'])
        ->assertRedirect();

    expect($file->fresh()->alt_text)->toBe('The team at the summer party');
});

describe('serving files', function () {
    /**
     * Writes a real file into the tenant's public storage, where the asset route reads from
     */
    function putTenantAsset(string $path, string $contents): string
    {
        $fullPath = storage_path('app/public/'.$path);
        @mkdir(dirname($fullPath), 0755, true);
        file_put_contents($fullPath, $contents);

        return $fullPath;
    }

    test('SVGs are sandboxed when opened directly, so scripts inside them cannot run', function () {
        $fullPath = putTenantAsset('file-manager/test-sandbox.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $response = $this->get(route('stancl.tenancy.asset', ['path' => 'file-manager/test-sandbox.svg']))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        expect((string) $response->headers->get('Content-Security-Policy'))->toContain('sandbox');

        unlink($fullPath);
    });

    test('other files are not sandboxed, so PDFs still display, but types are never sniffed', function () {
        $fullPath = putTenantAsset('file-manager/test-sandbox.txt', 'hello');

        $this->get(route('stancl.tenancy.asset', ['path' => 'file-manager/test-sandbox.txt']))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeaderMissing('Content-Security-Policy');

        unlink($fullPath);
    });
});
