<?php

namespace App\Http\Queries\Tenant\Admin\Dj;

use App\Enums\Dj\RequestStatus;
use App\Enums\Dj\SetlistSongType;
use App\Models\Dj\Gig;
use App\Models\Dj\SongList;
use App\Models\Dj\SongListItem;
use App\Services\Dj\Requests\SongKeys;
use Illuminate\Database\Eloquent\Collection;

/**
 * A gig's song requests for the DJ, by status: pending by votes then age, accepted, played (newest
 * first) and declined. Each request's setlist flags (must play, maybe, do not play) come from the
 * gig's setlists as they are now.
 */
class RequestQueueQuery
{
    public function __construct(protected SongKeys $keys) {}

    /**
     * @return array{items: array<string, Collection<int, SongListItem>>, setlistTypes: array<int, list<SetlistSongType>>}
     */
    public function handle(Gig $gig): array
    {
        $list = $gig->requestsList;
        $items = $list === null ? new Collection : SongListItem::query()
            ->where('song_list_id', $list->id)
            ->whereNotNull('status')
            ->with(['song.artist', 'guestPass'])
            ->withCount('votes')
            ->get();

        $byStatus = fn (RequestStatus $status): Collection => $items->where('status', $status)->values();

        return [
            'items' => [
                RequestStatus::Pending->value => $byStatus(RequestStatus::Pending)
                    ->sortBy([['votes_count', 'desc'], ['created_at', 'asc'], ['id', 'asc']])->values(),
                RequestStatus::Accepted->value => $byStatus(RequestStatus::Accepted)
                    ->sortBy([['votes_count', 'desc'], ['created_at', 'asc']])->values(),
                RequestStatus::Played->value => $byStatus(RequestStatus::Played)->sortByDesc('played_at')->values(),
                RequestStatus::Declined->value => $byStatus(RequestStatus::Declined)->sortByDesc('updated_at')->values(),
            ],
            'setlistTypes' => $this->setlistTypes($gig, $items),
        ];
    }

    /**
     * Each request's types on the gig's setlists, matched as the same song (see SongKeys)
     *
     * @param  Collection<int, SongListItem>  $requests
     * @return array<int, list<SetlistSongType>>
     */
    protected function setlistTypes(Gig $gig, Collection $requests): array
    {
        /** @var array<string, array<string, SetlistSongType>> $typesByKey */
        $typesByKey = [];

        SongListItem::query()
            ->whereIn('song_list_id', SongList::query()->setlists()->where('gig_id', $gig->id)->select('id'))
            ->whereNotNull('type')
            ->get()
            ->each(function (SongListItem $item) use (&$typesByKey): void {
                if ($item->type instanceof SetlistSongType) {
                    $typesByKey[$this->keys->forItem($item)][$item->type->value] = $item->type;
                }
            });

        $types = [];

        foreach ($requests as $request) {
            $types[$request->id] = array_values($typesByKey[$this->keys->forItem($request)] ?? []);
        }

        return $types;
    }
}
