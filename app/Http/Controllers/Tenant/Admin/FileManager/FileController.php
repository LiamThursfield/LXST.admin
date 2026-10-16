<?php

namespace App\Http\Controllers\Tenant\Admin\FileManager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\FileManager\FileStoreRequest;
use App\Http\Requests\Tenant\Admin\FileManager\FileUpdateRequest;
use App\Models\FileManager\File;
use App\Services\FileManager\FileManagerStorage;
use Illuminate\Http\RedirectResponse;

class FileController extends Controller
{
    /**
     * Uploads one or more files into a folder (or the top level)
     */
    public function store(FileStoreRequest $request, FileManagerStorage $storage): RedirectResponse
    {
        $folder = $request->folder();
        $uploads = $request->uploads();

        foreach ($uploads as $upload) {
            $storage->storeUpload($upload, $folder);
        }

        return redirect()->route('admin.file-manager.index', $folder ? ['folder' => $folder->id] : [])
            ->with('success', trans_choice('file_manager.files.flash.uploaded', count($uploads), ['count' => count($uploads)]));
    }

    /**
     * Updates a file's details (its alt text)
     */
    public function update(FileUpdateRequest $request, File $file): RedirectResponse
    {
        $file->update(['alt_text' => $request->validated('alt_text')]);

        return redirect()->back()->with('success', __('file_manager.files.flash.updated'));
    }
}
