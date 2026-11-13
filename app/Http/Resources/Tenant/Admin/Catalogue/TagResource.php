<?php

namespace App\Http\Resources\Tenant\Admin\Catalogue;

use App\Http\Resources\Traits\HasActions;
use App\Models\Catalogue\Tag;
use App\Support\UI\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TagResource extends JsonResource
{
    use HasActions;

    /**
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        /** @var Tag $tag */
        $tag = $this->resource;
        $user = $request->user();
        $actions = [];

        if ($user?->can('update', $tag)) {
            $actions[] = Action::make(__('common.actions.edit'), route('admin.catalogue.tags.edit', $tag))
                ->icon('i-lucide-pencil');
        }

        if ($user?->can('delete', $tag)) {
            $actions[] = Action::make(__('common.actions.delete'), route('admin.catalogue.tags.destroy', $tag))
                ->icon('i-lucide-trash')
                ->method('DELETE')
                ->requireConfirmation(__('catalogue.tags.confirm_delete', ['name' => $tag->name]));
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
        /** @var Tag $tag */
        $tag = $this->resource;

        return $this->appendActions($request, [
            'id' => $tag->id,
            'preset' => $tag->preset,
            'kind' => $tag->kind->value,
            'kind_label' => $tag->kind->label(),
            'name' => $tag->name,
            'icon' => $tag->icon,
            'order' => $tag->order,
            'items_count' => $tag->items_count ?? null,
        ]);
    }
}
