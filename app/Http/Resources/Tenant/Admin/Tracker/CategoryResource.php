<?php

namespace App\Http\Resources\Tenant\Admin\Tracker;

use App\Http\Resources\Traits\HasActions;
use App\Models\Tracker\Category;
use App\Support\UI\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    use HasActions;

    /**
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        /** @var Category $category */
        $category = $this->resource;

        $user = $request->user();
        $actions = [];

        if ($user?->can('update', $category)) {
            $actions[] = Action::make('Edit', route('admin.tracker.categories.edit', $category))
                ->icon('i-lucide-pencil');
        }

        if ($user?->can('delete', $category)) {
            $actions[] = Action::make('Delete', route('admin.tracker.categories.destroy', $category))
                ->icon('i-lucide-trash')
                ->method('DELETE')
                ->requireConfirmation("Are you sure you want to delete category $category->name?");
        }

        if (empty($actions) && $user?->can('view', $category)) {
            $actions[] = Action::make('View', route('admin.tracker.categories.show', $category))
                ->icon('i-lucide-eye');
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
        /** @var Category $category */
        $category = $this->resource;

        return $this->appendActions($request, [
            'id' => $category->id,
            'name' => $category->name,
            'colour' => $category->colour,
            'icon' => $category->icon,
            'is_public' => $category->is_public,
            'user_id' => $category->user_id,
        ]);
    }
}
