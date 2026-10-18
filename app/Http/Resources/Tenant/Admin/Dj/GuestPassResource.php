<?php

namespace App\Http\Resources\Tenant\Admin\Dj;

use App\Models\Dj\GuestPass;
use App\Models\Settings\DjSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A guest pass, for those who manage it, so the password is included to send again
 */
class GuestPassResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var GuestPass $pass */
        $pass = $this->resource;

        return [
            'id' => $pass->id,
            'label' => $pass->label,
            'password' => $pass->password,
            'can_add' => $pass->can_add,
            'status' => match (true) {
                $pass->isRevoked() => 'revoked',
                $pass->isExpired() => 'expired',
                default => 'active',
            },
            // The last day it works, on the DJ's clock
            'expires_on' => $pass->expires_at?->setTimezone(app(DjSettings::class)->default_timezone)->toDateString(),
            'last_used_at' => $pass->last_used_at?->toIso8601String(),
            'items_count' => (int) $pass->getAttribute('items_count'),
        ];
    }
}
