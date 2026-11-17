<?php

namespace App\Services\Cms;

use App\Http\Requests\Tenant\Admin\Cms\PageStoreRequest;
use App\Http\Requests\Tenant\Admin\Cms\PageUpdateRequest;
use App\Models\Cms\Page;
use Illuminate\Support\Facades\DB;

/**
 * Saves a validated page: its details, URL and publishing window, metadata and content. Used by
 * the admin and the MCP server, so both save pages the same way.
 */
class PageWriter
{
    public function __construct(protected PageUrls $urls) {}

    public function create(PageStoreRequest $request): Page
    {
        return DB::transaction(function () use ($request) {
            $page = Page::create($request->pageAttributes());

            $this->urls->sync($page, $request->publishingAttributes());
            $page->metadata()->create($request->metadataAttributes());
            $page->syncContent($request->contentValues());

            return $page;
        });
    }

    public function update(Page $page, PageUpdateRequest $request): Page
    {
        return DB::transaction(function () use ($request, $page) {
            $page->update($request->pageAttributes());

            // Rebuilds the children's URLs too, if the slug or parent changed
            $this->urls->sync($page, $request->publishingAttributes());
            $page->metadata()->updateOrCreate([], $request->metadataAttributes());
            $page->syncContent($request->contentValues());

            return $page;
        });
    }
}
