<?php

namespace App\Http\Resources\Tenant\Admin\Bookings;

use App\Http\Resources\Traits\HasActions;
use App\Models\Bookings\ServiceCategory;
use App\Services\Industry\Terminology;
use App\Support\UI\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceCategoryResource extends JsonResource
{
    use HasActions;

    /**
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        /** @var ServiceCategory $category */
        $category = $this->resource;
        $user = $request->user();
        $actions = [];

        if ($user?->can('update', $category)) {
            $actions[] = Action::make(__('common.actions.edit'), route('admin.bookings.service-categories.edit', $category))
                ->icon('i-lucide-pencil');
        }

        if ($user?->can('delete', $category)) {
            $actions[] = Action::make(__('common.actions.delete'), route('admin.bookings.service-categories.destroy', $category))
                ->icon('i-lucide-trash')
                ->method('DELETE')
                ->requireConfirmation(app(Terminology::class)->translate('bookings.categories.confirm_delete', ['name' => $category->name]));
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
        /** @var ServiceCategory $category */
        $category = $this->resource;

        return $this->appendActions($request, [
            'id' => $category->id,
            'name' => $category->name,
            'order' => $category->order,
            'services_count' => $category->services_count ?? null,
        ]);
    }
}
