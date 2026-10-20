<?php

namespace App\Http\Resources\Tenant\Api\V1\Dj;

use App\Enums\Dj\RequestsMode;
use App\Enums\Dj\SongListKind;
use App\Http\Queries\Tenant\Api\V1\Dj\GigTracklistQuery;
use App\Models\Dj\Gig;
use App\Models\Dj\GuestPass;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A gig's public details and request settings, for a site to show a request form. Private fields
 * (notes, the database ID and the pending request limit) are left out.
 */
class PublicGigResource extends JsonResource
{
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
        $settings = $gig->requestSettings();
        $scheduled = $settings->requests_mode === RequestsMode::Scheduled;
        $nowPlaying = $settings->show_tracklist ? app(GigTracklistQuery::class)->nowPlaying($gig, $settings) : null;

        return [
            'id' => $gig->public_id,
            'name' => $gig->name,
            'venue' => $gig->venue,
            'location' => $gig->location,
            'starts_at' => $gig->starts_at->toIso8601String(),
            'ends_at' => $gig->ends_at->toIso8601String(),
            'timezone' => $gig->timezone,
            'is_on_now' => $gig->isOnAt($now),
            'requests' => [
                'open' => $gig->requestsOpenAt($now),
                'mode' => $settings->requests_mode->value,
                // When scheduled requests open and close
                'opens_at' => $scheduled ? $gig->starts_at->subMinutes($settings->requests_open_minutes_before)->toIso8601String() : null,
                'closes_at' => $scheduled ? $gig->ends_at->toIso8601String() : null,
                // The requests list, whose guest passes are exchanged for a token (#147)
                'list_id' => $gig->requestsList?->public_id,
                // Whether a guest pass is needed to request (#147)
                'requires_pass' => GuestPass::query()->active()
                    ->whereHas('songList', fn ($lists) => $lists->where('gig_id', $gig->id)->where('kind', SongListKind::Requests))
                    ->exists(),
                'allow_custom_requests' => $settings->allow_custom_requests,
                // 0 means no limit
                'max_requests_per_visitor' => $settings->max_requests_per_visitor,
                'show_public_list' => $settings->show_public_list,
                'allow_upvotes' => $settings->allow_upvotes,
            ],
            // What's playing now, when the gig shows its tracklist (#125); the rest is at /tracklist
            'tracklist' => [
                'enabled' => $settings->show_tracklist,
                'now_playing' => $nowPlaying === null ? null : (new PublicTrackResource($nowPlaying))->resolve($request),
            ],
        ];
    }
}
