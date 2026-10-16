<?php

use App\Models\FileManager\File;
use App\Models\FileManager\Folder;
use App\Services\Authorisation\Enums\FileManagerPermission;
use App\Services\Authorisation\Enums\Role as RoleEnum;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('file_manager');
});

describe('storage', function () {
    test('the file manager disk is scoped to the current tenant', function () {
        expect(config('filesystems.disks.file_manager.root'))
            ->toContain(config('tenancy.filesystem.suffix_base').tenant()->getTenantKey())
            ->toEndWith('app/public/file-manager/');
    });

    test('local files are served through the tenant asset route', function () {
        $this->actingAs($this->createUserWithPermissions([FileManagerPermission::ViewFiles]));
        $file = File::factory()->create(['name' => 'logo.png', 'path' => 'logo.png']);

        $this->get(route('admin.file-manager.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('files.data.0.url', tenant_asset('file-manager/logo.png'))
            );
    });
});

describe('access', function () {
    test('users without the view permission cannot browse files', function () {
        $this->actingAs($this->createUserWithPermissions([]));

        $this->get(route('admin.file-manager.index'))->assertForbidden();
    });

    test('users with only the view permission can browse but not create folders', function () {
        $this->actingAs($this->createUserWithPermissions([FileManagerPermission::ViewFiles]));

        $this->get(route('admin.file-manager.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/file-manager/Index')
                ->where('canCreateFolders', false)
            );

        $this->post(route('admin.file-manager.folders.store'), ['name' => 'images'])->assertForbidden();
    });

    test('folders cannot be created when uploads are disabled', function () {
        config(['file-manager.uploads_enabled' => false]);
        $this->actingAs($this->createUserWithPermissions(FileManagerPermission::cases()));

        $this->post(route('admin.file-manager.folders.store'), ['name' => 'images'])->assertForbidden();
    });

    test('admins and editors can manage files, and viewers can only view them', function () {
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach ([RoleEnum::Admin, RoleEnum::Editor] as $role) {
            expect(Role::findByName($role->value)->hasPermissionTo(FileManagerPermission::ManageFiles->value))->toBeTrue();
        }

        $viewer = Role::findByName(RoleEnum::Viewer->value);

        expect($viewer->hasPermissionTo(FileManagerPermission::ViewFiles->value))->toBeTrue()
            ->and($viewer->hasPermissionTo(FileManagerPermission::ManageFiles->value))->toBeFalse();
    });
});

describe('browsing', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(FileManagerPermission::cases()));
    });

    test('the top level shows its folders and files, and the full folder tree', function () {
        $images = Folder::factory()->create(['name' => 'images', 'path' => 'images']);
        Folder::factory()->in($images)->create(['name' => 'team']);
        File::factory()->create(['name' => 'brochure.pdf', 'path' => 'brochure.pdf', 'mime_type' => 'application/pdf']);
        File::factory()->in($images)->create();

        $this->get(route('admin.file-manager.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentFolder', null)
                ->has('subfolders', 1)
                ->where('subfolders.0.name', 'images')
                ->has('files.data', 1)
                ->where('files.data.0.name', 'brochure.pdf')
                ->where('files.data.0.is_image', false)
                ->where('folderTree.0.name', 'images')
                ->where('folderTree.0.children.0.name', 'team')
            );
    });

    test('a folder shows its own contents and breadcrumbs', function () {
        $images = Folder::factory()->create(['name' => 'images', 'path' => 'images']);
        $team = Folder::factory()->in($images)->create(['name' => 'team']);
        File::factory()->in($team)->create(['name' => 'jane.jpg']);

        $this->get(route('admin.file-manager.index', ['folder' => $team->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentFolder.path', 'images/team')
                ->where('breadcrumbs', [['id' => $images->id, 'name' => 'images'], ['id' => $team->id, 'name' => 'team']])
                ->has('subfolders', 0)
                ->has('files.data', 1)
                ->where('files.data.0.name', 'jane.jpg')
            );
    });

    test('an unknown folder is a validation error', function () {
        $this->get(route('admin.file-manager.index', ['folder' => 999]))->assertSessionHasErrors('folder');
    });
});

describe('creating folders', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(FileManagerPermission::cases()));
    });

    test('a folder is created on the disk and recorded, inside its parent', function () {
        $images = Folder::factory()->create(['name' => 'images', 'path' => 'images']);
        Storage::disk('file_manager')->makeDirectory('images');

        $this->post(route('admin.file-manager.folders.store'), ['parent_id' => $images->id, 'name' => 'Team photos'])
            ->assertRedirect();

        $folder = Folder::query()->where('name', 'Team photos')->sole();

        expect($folder->path)->toBe('images/Team photos')
            ->and($folder->parent_id)->toBe($images->id);

        Storage::disk('file_manager')->assertExists('images/Team photos');
    });

    test('folder names are checked for characters and are unique (ignoring case) within their parent', function () {
        $images = Folder::factory()->create(['name' => 'images', 'path' => 'images']);

        foreach (['a/b', '.hidden', ''] as $name) {
            $this->post(route('admin.file-manager.folders.store'), ['name' => $name])->assertSessionHasErrors('name');
        }

        $this->post(route('admin.file-manager.folders.store'), ['name' => 'Images'])->assertSessionHasErrors('name');

        // The same name is fine inside another folder
        $this->post(route('admin.file-manager.folders.store'), ['parent_id' => $images->id, 'name' => 'images'])
            ->assertSessionHasNoErrors();
    });
});
