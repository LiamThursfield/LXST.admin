<?php

namespace App\Http\Resources\Tenant\Admin\SiteSettings;

use App\Http\Resources\Traits\HasActions;
use App\Models\ApiToken;
use App\Support\UI\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiTokenResource extends JsonResource
{
    use HasActions;

    /**
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        /** @var ApiToken $token */
        $token = $this->resource;
        $actions = [];

        if ($request->user()?->can('delete', $token)) {
            $actions[] = Action::make(__('site_settings.api_tokens.revoke'), route('admin.site-settings.api-tokens.destroy', $token))
                ->icon('i-lucide-ban')
                ->method('DELETE')
                ->requireConfirmation(__('site_settings.api_tokens.confirm_revoke', ['name' => $token->name]));
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
        /** @var ApiToken $token */
        $token = $this->resource;

        return $this->appendActions($request, [
            'id' => $token->id,
            'name' => $token->name,
            'abilities' => array_map(fn ($ability) => $ability->toSelectItem(), $token->abilityEnums()),
            'owner' => $token->tokenable?->name,
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
            'is_expired' => $token->isExpired(),
            'created_at' => $token->created_at->toIso8601String(),
        ]);
    }
}
