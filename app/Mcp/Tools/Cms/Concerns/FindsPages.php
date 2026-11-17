<?php

namespace App\Mcp\Tools\Cms\Concerns;

use App\Mcp\Support\ToolRefused;
use App\Models\Cms\Page;
use App\Models\Cms\Url;
use Laravel\Mcp\Request;

trait FindsPages
{
    /**
     * The page given by `id`, or by its full `url`, e.g. "/about/team"
     */
    protected function findPage(Request $request): Page
    {
        $validated = $request->validate([
            'id' => ['nullable', 'integer'],
            'url' => ['nullable', 'string', 'max:255'],
        ]);

        if (! isset($validated['id']) && ! isset($validated['url'])) {
            throw new ToolRefused(__('mcp.errors.id_or_slug'));
        }

        $page = isset($validated['id'])
            ? Page::query()->find((int) $validated['id'])
            : Url::query()
                ->where('url_full', '/'.trim((string) $validated['url'], '/'))
                ->where('urlable_type', (new Page)->getMorphClass())
                ->first()
                ?->urlable;

        if (! $page instanceof Page) {
            throw new ToolRefused(__('mcp.errors.not_found', ['type' => 'page']));
        }

        return $page;
    }
}
