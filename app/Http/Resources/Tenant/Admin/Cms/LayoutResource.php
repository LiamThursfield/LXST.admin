<?php

namespace App\Http\Resources\Tenant\Admin\Cms;

use App\Http\Resources\Traits\HasActions;
use App\Models\Cms\Layout;
use App\Support\UI\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LayoutResource extends JsonResource
{
    use HasActions;

    /**
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        /** @var Layout $layout */
        $layout = $this->resource;
        $user = $request->user();
        $actions = [];

        if ($user?->can('view', $layout)) {
            $actions[] = Action::make(__('common.actions.view'), route('admin.cms.layouts.show', $layout))
                ->icon('i-lucide-eye');
        }

        if ($user?->can('update', $layout)) {
            $actions[] = Action::make(__('common.actions.edit'), route('admin.cms.layouts.edit', $layout))
                ->icon('i-lucide-pencil');
        }

        if ($user?->can('delete', $layout)) {
            $actions[] = Action::make(__('common.actions.delete'), route('admin.cms.layouts.destroy', $layout))
                ->icon('i-lucide-trash')
                ->method('DELETE')
                ->requireConfirmation(__('cms.layouts.confirm_delete', ['name' => $layout->name]));
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
        /** @var Layout $layout */
        $layout = $this->resource;

        return $this->appendActions($request, [
            'id' => $layout->id,
            'template_id' => $layout->template_id,
            'name' => $layout->name,
            'slug' => $layout->slug,
            'template' => $this->whenLoaded('template', fn () => [
                'id' => $layout->template->id,
                'name' => $layout->template->name,
            ]),
            // Keyed by field slug
            'content' => $this->whenLoaded('content', fn () => (object) $layout->contentValues()),
        ]);
    }
}
