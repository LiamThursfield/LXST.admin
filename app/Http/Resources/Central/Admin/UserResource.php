<?php

namespace App\Http\Resources\Central\Admin;

use App\Http\Resources\Traits\HasActions;
use App\Models\User;
use App\Support\UI\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    use HasActions;

    /**
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        $actions = [
            Action::make(__('common.actions.edit'), route('central.admin.users.edit', $user))
                ->icon('i-lucide-pencil'),
        ];

        // People delete their own account from their profile settings
        if (! $user->is($request->user())) {
            $actions[] = Action::make(__('common.actions.delete'), route('central.admin.users.destroy', $user))
                ->icon('i-lucide-trash')
                ->method('DELETE')
                ->requireConfirmation(__('users.confirm_delete', ['email' => $user->email]));
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
        /** @var User $user */
        $user = $this->resource;

        return $this->appendActions($request, [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'is_you' => $user->is($request->user()),
        ]);
    }
}
