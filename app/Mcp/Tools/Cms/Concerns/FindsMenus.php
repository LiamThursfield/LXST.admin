<?php

namespace App\Mcp\Tools\Cms\Concerns;

use App\Mcp\Support\ToolRefused;
use App\Models\Cms\Menu;
use Laravel\Mcp\Request;

trait FindsMenus
{
    /**
     * The menu given by `id` or `slug`
     */
    protected function findMenu(Request $request): Menu
    {
        $validated = $request->validate([
            'id' => ['nullable', 'integer'],
            'slug' => ['nullable', 'string', 'max:120'],
        ]);

        if (! isset($validated['id']) && ! isset($validated['slug'])) {
            throw new ToolRefused(__('mcp.errors.id_or_slug'));
        }

        $menu = isset($validated['id'])
            ? Menu::query()->find((int) $validated['id'])
            : Menu::query()->where('slug', $validated['slug'])->first();

        if (! $menu instanceof Menu) {
            throw new ToolRefused(__('mcp.errors.not_found', ['type' => 'menu']));
        }

        return $menu;
    }
}
