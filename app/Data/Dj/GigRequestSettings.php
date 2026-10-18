<?php

namespace App\Data\Dj;

use App\Enums\Dj\RequestsMode;
use App\Models\Settings\DjSettings;
use Spatie\LaravelData\Data;

/**
 * The request settings one gig overrides. Null means "use the DJ settings default", so
 * changing a default changes every gig that hasn't overridden it.
 */
class GigRequestSettings extends Data
{
    public function __construct(
        public ?RequestsMode $requests_mode = null,
        /** How long before the start requests open, when scheduled */
        public ?int $requests_open_minutes_before = null,
        /** 0 means no limit */
        public ?int $max_requests_per_visitor = null,
        /** 0 means no limit */
        public ?int $max_pending_requests = null,
        public ?bool $allow_custom_requests = null,
        public ?bool $show_public_list = null,
        public ?bool $allow_upvotes = null,
        public ?bool $show_pass_labels = null,
    ) {}

    /**
     * Every setting, with the defaults filled in where this gig doesn't override them
     */
    public function effective(DjSettings $defaults): EffectiveRequestSettings
    {
        return new EffectiveRequestSettings(
            requests_mode: $this->requests_mode ?? $defaults->requests_mode,
            requests_open_minutes_before: $this->requests_open_minutes_before ?? $defaults->requests_open_minutes_before,
            max_requests_per_visitor: $this->max_requests_per_visitor ?? $defaults->max_requests_per_visitor,
            max_pending_requests: $this->max_pending_requests ?? $defaults->max_pending_requests,
            allow_custom_requests: $this->allow_custom_requests ?? $defaults->allow_custom_requests,
            show_public_list: $this->show_public_list ?? $defaults->show_public_list,
            allow_upvotes: $this->allow_upvotes ?? $defaults->allow_upvotes,
            show_pass_labels: $this->show_pass_labels ?? $defaults->show_pass_labels,
        );
    }
}
