<?php

namespace App\Http\Controllers\Tenant\Admin\Dj;

use App\Data\Dj\GigRequestSettings;
use App\Enums\Dj\RequestsMode;
use App\Enums\Dj\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Dj\RequestQueueQuery;
use App\Http\Requests\Tenant\Admin\Dj\RequestsModeUpdateRequest;
use App\Http\Requests\Tenant\Admin\Dj\RequestStatusUpdateRequest;
use App\Http\Resources\Tenant\Admin\Dj\RequestQueueItemResource;
use App\Models\Dj\Gig;
use App\Models\Dj\SongListItem;
use App\Models\Settings\DjSettings;
use App\Services\Dj\CurrentGig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The DJ's request queue during a gig, in the admin or full screen in the booth
 */
class RequestQueueController extends Controller
{
    public function __construct(
        protected CurrentGig $currentGig,
        protected RequestQueueQuery $query,
        protected DjSettings $settings,
    ) {}

    public function index(Request $request): Response
    {
        return $this->render('admin/dj/requests/Index', $request);
    }

    /**
     * Big text and buttons, without the sidebar, to leave open in the booth
     */
    public function booth(Request $request): Response
    {
        return $this->render('admin/dj/requests/Booth', $request);
    }

    /**
     * Accepting, declining or marking played; pending again undoes any of them
     */
    public function updateStatus(RequestStatusUpdateRequest $request, SongListItem $item): RedirectResponse
    {
        $status = $request->status();

        // The DJ API's cached request lists refresh with any DJ admin change (content.changed:dj)
        $item->update([
            'status' => $status,
            'played_at' => $status === RequestStatus::Played ? now() : null,
        ]);

        return redirect()->back();
    }

    /**
     * Opens or closes the gig's requests, overriding DJ settings, or follows them again
     */
    public function updateMode(RequestsModeUpdateRequest $request, Gig $gig): RedirectResponse
    {
        $gig->update([
            'request_settings' => GigRequestSettings::from([
                ...($gig->request_settings?->toArray() ?? []),
                'requests_mode' => $request->mode(),
            ]),
        ]);

        return redirect()->back()->with('success', __($gig->requestsOpenAt(now()) ? 'dj.queue.flash.opened' : 'dj.queue.flash.closed'));
    }

    protected function render(string $component, Request $request): Response
    {
        $settings = $this->settings;
        Gate::authorize('viewRequests', SongListItem::class);

        $validated = $request->validate(['gig' => ['nullable', 'integer']]);
        $gig = isset($validated['gig'])
            ? Gig::query()->whereKey((int) $validated['gig'])->firstOrFail()
            : $this->currentGig->resolve();

        $queue = $gig === null ? null : $this->query->handle($gig);

        return Inertia::render($component, [
            'gig' => $gig === null ? null : [
                'id' => $gig->id,
                'name' => $gig->name,
                'timezone' => $gig->timezone,
                'starts_at' => $gig->starts_at->toIso8601String(),
                'ends_at' => $gig->ends_at->toIso8601String(),
                'requests_open' => $gig->requestsOpenAt(now()),
                // The gig's own override: open, closed, or null to follow DJ settings
                'requests_mode' => $gig->request_settings?->requests_mode?->value,
                // What "back to default" means, from DJ settings
                'default_requests_mode' => $settings->requests_mode->label(),
                'edit_url' => route('admin.dj.gigs.edit', $gig),
            ],
            'requests' => $queue === null ? null : collect($queue['items'])->map(
                fn ($items) => $items->map(fn (SongListItem $item) => (new RequestQueueItemResource($item, $queue['setlistTypes'][$item->id] ?? []))->resolve($request))->all()
            ),
            'gigs' => Gig::selectItems(),
            'modes' => [RequestsMode::Open->toSelectItem(), RequestsMode::Closed->toSelectItem()],
        ]);
    }
}
