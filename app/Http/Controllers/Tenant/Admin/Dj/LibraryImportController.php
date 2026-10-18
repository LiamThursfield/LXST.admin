<?php

namespace App\Http\Controllers\Tenant\Admin\Dj;

use App\Enums\Dj\ImportStatus;
use App\Enums\Dj\LibrarySource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Dj\LibraryImportStoreRequest;
use App\Jobs\Dj\ImportLibrary;
use App\Models\Dj\Library;
use App\Models\Dj\LibraryImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class LibraryImportController extends Controller
{
    /**
     * Keeps the upload privately until the queued import has finished with it
     */
    public function store(LibraryImportStoreRequest $request): RedirectResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $path = $file->store('dj-imports', ['disk' => config('dj.imports.disk')]);

        /** @var LibrarySource $source */
        $source = $request->source();

        $import = DB::transaction(function () use ($request, $file, $path, $source): LibraryImport {
            $library = $request->library() ?? Library::query()->create([
                'name' => $request->validated('library_name'),
                'source' => $source,
            ]);

            return $library->imports()->create([
                'user_id' => $request->user()?->getKey(),
                'source' => $source,
                'status' => ImportStatus::Pending,
                'original_filename' => $file->getClientOriginalName(),
                'file_path' => $path,
            ]);
        });

        ImportLibrary::dispatch($import);

        return redirect()->route('admin.dj.libraries.index')
            ->with('success', __('dj.imports.flash.started'));
    }
}
