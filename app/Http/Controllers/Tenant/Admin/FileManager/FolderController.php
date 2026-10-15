<?php

namespace App\Http\Controllers\Tenant\Admin\FileManager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\FileManager\FolderStoreRequest;
use App\Services\FileManager\FileManagerStorage;
use Illuminate\Http\RedirectResponse;

class FolderController extends Controller
{
    public function store(FolderStoreRequest $request, FileManagerStorage $storage): RedirectResponse
    {
        $folder = $storage->createFolder($request->folderName(), $request->parent());

        return redirect()->route('admin.file-manager.index', ['folder' => $folder->id])
            ->with('success', __('file_manager.folders.flash.created'));
    }
}
