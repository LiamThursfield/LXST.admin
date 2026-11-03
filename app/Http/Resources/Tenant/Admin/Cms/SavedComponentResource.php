<?php

namespace App\Http\Resources\Tenant\Admin\Cms;

use App\Http\Resources\Traits\HasActions;
use App\Models\Cms\SavedComponent;
use App\Support\UI\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavedComponentResource extends JsonResource
{
    use HasActions;

    /**
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        /** @var SavedComponent $savedComponent */
        $savedComponent = $this->resource;
        $user = $request->user();
        $actions = [];

        if ($user?->can('view', $savedComponent)) {
            $actions[] = Action::make(__('common.actions.view'), route('admin.cms.saved-components.show', $savedComponent))
                ->icon('i-lucide-eye');
        }

        if ($user?->can('update', $savedComponent)) {
            $actions[] = Action::make(__('common.actions.edit'), route('admin.cms.saved-components.edit', $savedComponent))
                ->icon('i-lucide-pencil');
        }

        if ($user?->can('delete', $savedComponent)) {
            $actions[] = Action::make(__('common.actions.delete'), route('admin.cms.saved-components.destroy', $savedComponent))
                ->icon('i-lucide-trash')
                ->method('DELETE')
                ->requireConfirmation(__('cms.saved_components.confirm_delete', ['name' => $savedComponent->name]));
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
        /** @var SavedComponent $savedComponent */
        $savedComponent = $this->resource;

        return $this->appendActions($request, [
            'id' => $savedComponent->id,
            'template_id' => $savedComponent->template_id,
            'name' => $savedComponent->name,
            'slug' => $savedComponent->slug,
            'template' => $this->whenLoaded('template', fn () => [
                'id' => $savedComponent->template->id,
                'name' => $savedComponent->template->name,
            ]),
            // Keyed by field slug
            'content' => $this->whenLoaded('content', fn () => (object) $savedComponent->contentValues()),
        ]);
    }
}
