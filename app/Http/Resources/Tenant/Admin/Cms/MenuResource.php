<?php

namespace App\Http\Resources\Tenant\Admin\Cms;

use App\Http\Resources\Traits\HasActions;
use App\Models\Cms\Menu;
use App\Support\UI\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuResource extends JsonResource
{
    use HasActions;

    /**
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        /** @var Menu $menu */
        $menu = $this->resource;
        $user = $request->user();
        $actions = [];

        if ($user?->can('view', $menu)) {
            $actions[] = Action::make(__('common.actions.view'), route('admin.cms.menus.show', $menu))
                ->icon('i-lucide-eye');
        }

        if ($user?->can('update', $menu)) {
            $actions[] = Action::make(__('common.actions.edit'), route('admin.cms.menus.edit', $menu))
                ->icon('i-lucide-pencil');
        }

        if ($user?->can('delete', $menu)) {
            $actions[] = Action::make(__('common.actions.delete'), route('admin.cms.menus.destroy', $menu))
                ->icon('i-lucide-trash')
                ->method('DELETE')
                ->requireConfirmation(__('cms.menus.confirm_delete', ['name' => $menu->name]));
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
        /** @var Menu $menu */
        $menu = $this->resource;
        $items = $menu->menu_items?->toArray() ?? [];

        return $this->appendActions($request, [
            'id' => $menu->id,
            'name' => $menu->name,
            'slug' => $menu->slug,
            'items_count' => $this->countItems($items),
            'menu_items' => $items,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function countItems(array $items): int
    {
        return array_sum(array_map(
            fn (array $item) => 1 + $this->countItems(is_array($item['children'] ?? null) ? $item['children'] : []),
            $items,
        ));
    }
}
