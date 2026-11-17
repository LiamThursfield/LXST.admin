<?php

namespace App\Mcp\Tools\Cms\Concerns;

use App\Enums\Cms\TemplateType;
use App\Mcp\Support\ToolRefused;
use App\Models\Cms\Template;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;

trait FindsTemplates
{
    /**
     * The template given by `id`, or by `slug` and `type` (page by default)
     */
    protected function findTemplate(Request $request): Template
    {
        $validated = $request->validate([
            'id' => ['nullable', 'integer'],
            'slug' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', Rule::enum(TemplateType::class)],
        ]);

        if (! isset($validated['id']) && ! isset($validated['slug'])) {
            throw new ToolRefused(__('mcp.errors.id_or_slug'));
        }

        $template = isset($validated['id'])
            ? Template::query()->find((int) $validated['id'])
            : Template::query()->where('slug', $validated['slug'])->where('type', $validated['type'] ?? TemplateType::Page->value)->first();

        if (! $template instanceof Template) {
            throw new ToolRefused(__('mcp.errors.not_found', ['type' => 'template']));
        }

        return $template;
    }
}
