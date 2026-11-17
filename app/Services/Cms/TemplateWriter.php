<?php

namespace App\Services\Cms;

use App\Http\Controllers\Concerns\SyncsBuilderFields;
use App\Http\Controllers\Concerns\SyncsBuilderSections;
use App\Http\Requests\Tenant\Admin\Cms\TemplateStoreRequest;
use App\Http\Requests\Tenant\Admin\Cms\TemplateUpdateRequest;
use App\Models\Cms\Template;
use Illuminate\Support\Facades\DB;

/**
 * Saves a validated template with its sections and fields. Used by the admin and the MCP server,
 * so both save templates the same way.
 */
class TemplateWriter
{
    use SyncsBuilderFields;
    use SyncsBuilderSections;

    public function create(TemplateStoreRequest $request): Template
    {
        return DB::transaction(function () use ($request) {
            $template = Template::create($request->templateAttributes());

            $this->syncFields($template, $request);

            return $template;
        });
    }

    public function update(Template $template, TemplateUpdateRequest $request): Template
    {
        return DB::transaction(function () use ($request, $template) {
            $template->update($request->templateAttributes());

            $this->syncFields($template, $request);

            return $template;
        });
    }

    /**
     * Saves the sections first, so the fields can be placed in them
     */
    protected function syncFields(Template $template, TemplateStoreRequest|TemplateUpdateRequest $request): void
    {
        $sectionIds = $this->syncBuilderSections($template->sections(), $request->sectionsAttributes());

        $this->syncBuilderFields($template->fields(), $request->fieldsAttributes($sectionIds));
    }
}
