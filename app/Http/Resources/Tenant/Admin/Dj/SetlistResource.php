<?php

namespace App\Http\Resources\Tenant\Admin\Dj;

use App\Enums\Dj\SetlistSongType;
use App\Http\Resources\Traits\HasActions;
use App\Models\Dj\SongList;
use App\Support\UI\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A setlist, as listed. Expects `gig` loaded and the `*_count` counts per song type.
 */
class SetlistResource extends JsonResource
{
    use HasActions;

    /**
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        /** @var SongList $setlist */
        $setlist = $this->resource;
        $actions = [];

        if ($request->user()?->can('delete', $setlist)) {
            $actions[] = Action::make(__('common.actions.delete'), route('admin.dj.setlists.destroy', $setlist))
                ->icon('i-lucide-trash')
                ->method('DELETE')
                ->requireConfirmation(__('dj.setlists.confirm_delete', ['name' => $setlist->name]));
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
        /** @var SongList $setlist */
        $setlist = $this->resource;
        $gig = $setlist->gig;

        return $this->appendActions($request, [
            'id' => $setlist->id,
            'name' => $setlist->name,
            'description' => $setlist->description,
            'visibility' => $setlist->visibility->value,
            'visibility_label' => $setlist->visibility->label(),
            'gig' => $gig === null ? null : [
                'id' => $gig->id,
                'name' => $gig->name,
                'starts_at' => $gig->starts_at->toIso8601String(),
                'timezone' => $gig->timezone,
                'url' => route('admin.dj.gigs.edit', $gig),
            ],
            'counts' => collect(SetlistSongType::cases())->mapWithKeys(fn (SetlistSongType $type) => [
                $type->value => (int) $setlist->getAttribute("{$type->value}_count"),
            ])->all(),
            'url' => route('admin.dj.setlists.show', $setlist),
        ]);
    }
}
