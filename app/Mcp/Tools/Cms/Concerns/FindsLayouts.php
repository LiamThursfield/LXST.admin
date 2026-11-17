<?php

namespace App\Mcp\Tools\Cms\Concerns;

use App\Mcp\Support\ToolRefused;
use App\Models\Cms\Layout;
use Laravel\Mcp\Request;

trait FindsLayouts
{
    /**
     * The layout given by `id` or `slug`
     */
    protected function findLayout(Request $request): Layout
    {
        $validated = $request->validate([
            'id' => ['nullable', 'integer'],
            'slug' => ['nullable', 'string', 'max:120'],
        ]);

        if (! isset($validated['id']) && ! isset($validated['slug'])) {
            throw new ToolRefused(__('mcp.errors.id_or_slug'));
        }

        $layout = isset($validated['id'])
            ? Layout::query()->find((int) $validated['id'])
            : Layout::query()->where('slug', $validated['slug'])->first();

        if (! $layout instanceof Layout) {
            throw new ToolRefused(__('mcp.errors.not_found', ['type' => 'layout']));
        }

        return $layout;
    }
}
