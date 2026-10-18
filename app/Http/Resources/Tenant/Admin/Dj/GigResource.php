<?php

namespace App\Http\Resources\Tenant\Admin\Dj;

use App\Http\Resources\Traits\HasActions;
use App\Models\Dj\Gig;
use App\Support\UI\Action;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A gig, with its times on its own clock for the form, its effective request settings, and
 * whether it's the current gig (the controller sets the current gig's ID on the request, as
 * CURRENT_GIG_ATTRIBUTE, so it's only worked out once)
 */
class GigResource extends JsonResource
{
    use HasActions;

    public const string CURRENT_GIG_ATTRIBUTE = 'dj_current_gig_id';

    /**
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        /** @var Gig $gig */
        $gig = $this->resource;
        $user = $request->user();
        $actions = [];

        if ($user?->can('update', $gig)) {
            $actions[] = Action::make(__('common.actions.edit'), route('admin.dj.gigs.edit', $gig))
                ->icon('i-lucide-pencil');
        }

        if ($user?->can('delete', $gig)) {
            $actions[] = Action::make(__('common.actions.delete'), route('admin.dj.gigs.destroy', $gig))
                ->icon('i-lucide-trash')
                ->method('DELETE')
                ->requireConfirmation(__('dj.gigs.confirm_delete', ['name' => $gig->name]));
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
        /** @var Gig $gig */
        $gig = $this->resource;
        $now = CarbonImmutable::now();

        return $this->appendActions($request, [
            'id' => $gig->id,
            'name' => $gig->name,
            'venue' => $gig->venue,
            'location' => $gig->location,
            'timezone' => $gig->timezone,
            'starts_at' => $gig->starts_at->toIso8601String(),
            'ends_at' => $gig->ends_at->toIso8601String(),
            // On the gig's own clock, for datetime-local inputs
            'starts_at_local' => $gig->starts_at->setTimezone($gig->timezone)->format('Y-m-d\TH:i'),
            'ends_at_local' => $gig->ends_at->setTimezone($gig->timezone)->format('Y-m-d\TH:i'),
            'notes' => $gig->notes,
            'is_pinned' => $gig->is_pinned,
            'is_current' => $request->attributes->get(self::CURRENT_GIG_ATTRIBUTE) === $gig->id,
            'is_on_now' => $gig->isOnAt($now),
            'is_past' => $gig->ends_at->lessThanOrEqualTo($now),
            'requests_open' => $gig->requestsOpenAt($now),
            'public_id' => $gig->public_id,
            'request_url' => $gig->requestUrl(),
            'request_settings' => $gig->request_settings?->toArray() ?? [],
            'effective_request_settings' => $gig->requestSettings()->toArray(),
            'can_update' => $request->user()?->can('update', $gig) ?? false,
        ]);
    }
}
