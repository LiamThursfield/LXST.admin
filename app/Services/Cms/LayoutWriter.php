<?php

namespace App\Services\Cms;

use App\Http\Requests\Tenant\Admin\Cms\LayoutStoreRequest;
use App\Http\Requests\Tenant\Admin\Cms\LayoutUpdateRequest;
use App\Models\Cms\Layout;
use Illuminate\Support\Facades\DB;

/**
 * Saves a validated layout and its content. Used by the admin and the MCP server, so both save
 * layouts the same way.
 */
class LayoutWriter
{
    public function create(LayoutStoreRequest $request): Layout
    {
        return DB::transaction(function () use ($request) {
            $layout = Layout::create($request->layoutAttributes());

            $layout->syncContent($request->contentValues());

            return $layout;
        });
    }

    public function update(Layout $layout, LayoutUpdateRequest $request): Layout
    {
        return DB::transaction(function () use ($request, $layout) {
            $layout->update($request->layoutAttributes());

            // Content for fields that aren't in the (possibly changed) template is removed
            $layout->syncContent($request->contentValues());

            return $layout;
        });
    }
}
