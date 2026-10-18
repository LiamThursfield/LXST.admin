<?php

namespace App\Http\Resources\Tenant\Admin\Dj;

use App\Http\Resources\Traits\HasActions;
use App\Models\Dj\Library;
use App\Support\UI\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LibraryResource extends JsonResource
{
    use HasActions;

    /**
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        /** @var Library $library */
        $library = $this->resource;
        $actions = [];

        if ($request->user()?->can('delete', $library)) {
            $actions[] = Action::make(__('common.actions.delete'), route('admin.dj.libraries.destroy', $library))
                ->icon('i-lucide-trash')
                ->method('DELETE')
                ->requireConfirmation(__('dj.libraries.confirm_delete', ['name' => $library->name]));
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
        /** @var Library $library */
        $library = $this->resource;

        return $this->appendActions($request, [
            'id' => $library->id,
            'name' => $library->name,
            'source' => $library->source->value,
            'source_label' => $library->source->label(),
            'tracks_count' => (int) $library->getAttribute('present_tracks_count'),
            'missing_tracks_count' => (int) $library->getAttribute('missing_tracks_count'),
            'artists_count' => (int) $library->getAttribute('artists_count'),
            'is_hidden_from_requests' => $library->isHiddenFromRequests(),
            'history_sessions_count' => (int) $library->getAttribute('history_sessions_count'),
            'last_imported_at' => $library->getAttribute('last_imported_at')
                ? (string) $library->getAttribute('last_imported_at')
                : null,
            'can_import' => $request->user()?->can('import', $library) ?? false,
            'can_update' => $request->user()?->can('update', $library) ?? false,
        ]);
    }
}
