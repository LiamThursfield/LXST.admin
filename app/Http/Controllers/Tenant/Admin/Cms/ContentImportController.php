<?php

namespace App\Http\Controllers\Tenant\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Cms\ContentImportRequest;
use App\Services\Cms\Portable\ContentImporter;
use App\Services\Cms\Portable\ItemImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pasting portable JSON from another environment: a dry run first, then confirming
 */
class ContentImportController extends Controller
{
    public function __construct(protected ContentImporter $importer) {}

    public function show(ContentImportRequest $request): Response
    {
        return Inertia::render('admin/cms/import/Index');
    }

    /**
     * What importing would do, saving nothing
     */
    public function preview(ContentImportRequest $request): JsonResponse
    {
        return response()->json([
            'items' => $this->importer->preview($request->string('json')->toString(), $request->overwriteKeys()),
        ]);
    }

    public function store(ContentImportRequest $request): RedirectResponse
    {
        $results = collect($this->importer->import($request->string('json')->toString(), $request->overwriteKeys()));

        return redirect()->route('admin.cms.import.show')->with('success', __('cms.import.flash.imported', [
            'created' => $results->where('action', ItemImporter::ACTION_CREATE)->count(),
            'overwritten' => $results->where('action', ItemImporter::ACTION_OVERWRITE)->count(),
            'skipped' => $results->whereIn('action', [ItemImporter::ACTION_SKIP, ItemImporter::ACTION_UNCHANGED])->count(),
        ]));
    }
}
