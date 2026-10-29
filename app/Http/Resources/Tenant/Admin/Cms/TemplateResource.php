<?php

namespace App\Http\Resources\Tenant\Admin\Cms;

use App\Http\Resources\Traits\HasActions;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Models\Cms\TemplateSection;
use App\Support\UI\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemplateResource extends JsonResource
{
    use HasActions;

    /**
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        /** @var Template $template */
        $template = $this->resource;
        $user = $request->user();
        $actions = [];

        if ($user?->can('view', $template)) {
            $actions[] = Action::make(__('common.actions.view'), route('admin.cms.templates.show', $template))
                ->icon('i-lucide-eye');
        }

        if ($user?->can('update', $template)) {
            $actions[] = Action::make(__('common.actions.edit'), route('admin.cms.templates.edit', $template))
                ->icon('i-lucide-pencil');
        }

        if ($user?->can('delete', $template)) {
            $actions[] = Action::make(__('common.actions.delete'), route('admin.cms.templates.destroy', $template))
                ->icon('i-lucide-trash')
                ->method('DELETE')
                ->requireConfirmation(__('cms.templates.confirm_delete', ['name' => $template->name]));
        }

        return $actions;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Template $template */
        $template = $this->resource;

        return $this->appendActions($request, [
            'id' => $template->id,
            'type' => $template->type->value,
            'type_label' => $template->type->label(),
            'name' => $template->name,
            'slug' => $template->slug,
            'description' => $template->description,
            'fields_count' => $this->whenCounted('fields'),
            'fields' => $this->whenLoaded('fields', fn () => $template->fields->map(fn (TemplateField $field) => [
                'id' => $field->id,
                'section_id' => $field->section_id,
                'type' => $field->type->value,
                'type_label' => $field->type->label(),
                'name' => $field->name,
                'slug' => $field->slug,
                'description' => $field->description,
                'is_required' => $field->is_required,
                'settings' => (object) array_filter(
                    $field->settings?->toArray() ?? [],
                    fn (mixed $value) => $value !== null,
                ),
            ])->values()),
            'sections' => $this->whenLoaded('sections', fn () => $template->sections->map(fn (TemplateSection $section) => [
                'id' => $section->id,
                'name' => $section->name,
                'description' => $section->description,
                'is_collapsed' => $section->is_collapsed,
            ])->values()),
        ]);
    }
}
