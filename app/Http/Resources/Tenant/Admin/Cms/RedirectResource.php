<?php

namespace App\Http\Resources\Tenant\Admin\Cms;

use App\Http\Resources\Traits\HasActions;
use App\Models\Cms\Redirect;
use App\Models\User;
use App\Support\UI\Action;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RedirectResource extends JsonResource
{
    use HasActions;

    /**
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        /** @var Redirect $redirect */
        $redirect = $this->resource;
        $user = $request->user();
        $actions = [];

        if ($user?->can('update', $redirect)) {
            $actions[] = Action::make(__('common.actions.edit'), route('admin.cms.redirects.edit', $redirect))
                ->icon('i-lucide-pencil');
        }

        if ($user?->can('delete', $redirect)) {
            $actions[] = Action::make(__('common.actions.delete'), route('admin.cms.redirects.destroy', $redirect))
                ->icon('i-lucide-trash')
                ->method('DELETE')
                ->requireConfirmation(__('cms.redirects.confirm_delete', ['url' => $redirect->url_from]));
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
        /** @var Redirect $redirect */
        $redirect = $this->resource;
        $status = $redirect->status();

        return $this->appendActions($request, [
            'id' => $redirect->id,
            'url_from' => $redirect->url_from,
            'url_to' => $redirect->url_to,
            'is_external' => Redirect::isExternal($redirect->url_to),
            'is_enabled' => $redirect->is_enabled,
            'is_permanent' => $redirect->is_permanent,
            'http_status' => $redirect->httpStatus(),
            'status' => $status->value,
            'status_label' => $status->label(),
            'published_at' => $redirect->published_at?->toIso8601String(),
            'expired_at' => $redirect->expired_at?->toIso8601String(),
            // For datetime-local inputs, on the user's clock
            'published_at_local' => $this->localInput($redirect->published_at, $request),
            'expired_at_local' => $this->localInput($redirect->expired_at, $request),
        ]);
    }

    protected function localInput(?CarbonInterface $date, Request $request): ?string
    {
        $user = $request->user();

        if ($date === null || ! $user instanceof User) {
            return null;
        }

        return $date->copy()->setTimezone($user->localTimezone())->format('Y-m-d\TH:i');
    }
}
