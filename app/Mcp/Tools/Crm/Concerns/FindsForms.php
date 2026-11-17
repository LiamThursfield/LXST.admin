<?php

namespace App\Mcp\Tools\Crm\Concerns;

use App\Mcp\Support\ToolRefused;
use App\Models\Crm\Form;
use Laravel\Mcp\Request;

trait FindsForms
{
    /**
     * The form given by `id` or `slug`
     */
    protected function findForm(Request $request): Form
    {
        $validated = $request->validate([
            'id' => ['nullable', 'integer'],
            'slug' => ['nullable', 'string', 'max:120'],
        ]);

        if (! isset($validated['id']) && ! isset($validated['slug'])) {
            throw new ToolRefused(__('mcp.errors.id_or_slug'));
        }

        $form = isset($validated['id'])
            ? Form::query()->find((int) $validated['id'])
            : Form::query()->where('slug', $validated['slug'])->first();

        if (! $form instanceof Form) {
            throw new ToolRefused(__('mcp.errors.not_found', ['type' => 'form']));
        }

        return $form;
    }
}
