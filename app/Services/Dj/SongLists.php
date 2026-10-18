<?php

namespace App\Services\Dj;

use App\Enums\Dj\SongListKind;
use App\Enums\Dj\SongListVisibility;
use App\Models\Dj\Gig;
use App\Models\Dj\SongList;
use App\Models\Dj\SongListItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Adding to, reordering and copying song lists
 */
class SongLists
{
    /**
     * The gig's requests list, made the first time it's needed
     */
    public function requestsListFor(Gig $gig): SongList
    {
        return SongList::query()->firstOrCreate(
            ['gig_id' => $gig->id, 'kind' => SongListKind::Requests],
            ['name' => __('dj.guest_passes.requests_list_name', ['gig' => $gig->name]), 'visibility' => SongListVisibility::Private],
        );
    }

    /**
     * Adds a song at the end of the list
     *
     * @param  array<string, mixed>  $attributes
     */
    public function add(SongList $list, array $attributes, ?User $user = null): SongListItem
    {
        $position = (int) $list->items()->max('position') + 1;

        return $list->items()->create([
            ...$attributes,
            'position' => $position,
            'added_by_user_id' => $user?->id,
        ]);
    }

    /**
     * Puts the list's songs in the given order. Songs not given keep their place after them.
     *
     * @param  list<int>  $itemIds
     */
    public function reorder(SongList $list, array $itemIds): void
    {
        DB::transaction(function () use ($list, $itemIds): void {
            $ordered = array_values(array_unique($itemIds));
            $rest = $list->items()->whereNotIn('id', $ordered)->pluck('id')->all();

            foreach ([...$ordered, ...$rest] as $position => $id) {
                SongListItem::query()->whereKey($id)->where('song_list_id', $list->id)->update(['position' => $position + 1]);
            }
        });
    }

    /**
     * Copies a setlist's songs, types and notes into a new setlist. Who added each song isn't
     * copied, as the copy is made by the given user.
     */
    public function duplicate(SongList $list, string $name, ?Gig $gig, ?User $user = null): SongList
    {
        return DB::transaction(function () use ($list, $name, $gig, $user): SongList {
            $copy = SongList::query()->create([
                'gig_id' => $gig?->id,
                'kind' => $list->kind,
                'name' => $name,
                'description' => $list->description,
                'visibility' => $list->visibility,
            ]);

            foreach ($list->items as $item) {
                $copy->items()->create([
                    'canonical_track_id' => $item->canonical_track_id,
                    'custom_artist' => $item->custom_artist,
                    'custom_title' => $item->custom_title,
                    'type' => $item->type,
                    'position' => $item->position,
                    'note' => $item->note,
                    'added_by_user_id' => $user?->id,
                ]);
            }

            return $copy;
        });
    }
}
