<?php

namespace App\Http\Controllers\Tenant\Admin\Dj;

use App\Enums\Dj\SetlistSongType;
use App\Enums\Dj\SongListKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Dj\SetlistItemStoreRequest;
use App\Models\Dj\SongList;
use App\Models\Dj\SongListItem;
use App\Models\User;
use App\Services\Dj\SongLists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The songs on a setlist
 */
class SetlistItemController extends Controller
{
    public function store(SetlistItemStoreRequest $request, SongList $setlist, SongLists $songLists): RedirectResponse
    {
        abort_unless($setlist->kind === SongListKind::Setlist, HttpResponse::HTTP_NOT_FOUND);

        /** @var User $user */
        $user = $request->user();
        $songLists->add($setlist, $request->itemAttributes(), $user);

        return redirect()->back()->with('success', __('dj.setlists.flash.song_added'));
    }

    /**
     * Changes a song's type or note
     */
    public function update(Request $request, SongList $setlist, SongListItem $item): RedirectResponse
    {
        $this->authorizeItem($setlist, $item);

        $item->update($request->validate([
            'type' => ['sometimes', 'required', Rule::enum(SetlistSongType::class)],
            'note' => ['sometimes', 'nullable', Rule::string()->max(1000)],
        ]));

        return redirect()->back();
    }

    public function destroy(SongList $setlist, SongListItem $item): RedirectResponse
    {
        $this->authorizeItem($setlist, $item);

        $item->delete();

        return redirect()->back()->with('success', __('dj.setlists.flash.song_removed'));
    }

    /**
     * Puts the setlist's songs in the order given, e.g. after dragging
     */
    public function reorder(Request $request, SongList $setlist, SongLists $songLists): RedirectResponse
    {
        abort_unless($setlist->kind === SongListKind::Setlist, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('update', $setlist);

        /** @var list<int> $ids */
        $ids = $request->validate([
            'item_ids' => ['required', 'array', 'max:2000'],
            'item_ids.*' => ['integer'],
        ])['item_ids'];

        $songLists->reorder($setlist, $ids);

        return redirect()->back();
    }

    protected function authorizeItem(SongList $setlist, SongListItem $item): void
    {
        abort_unless($setlist->kind === SongListKind::Setlist && $item->song_list_id === $setlist->id, HttpResponse::HTTP_NOT_FOUND);
        Gate::authorize('update', $setlist);
    }
}
