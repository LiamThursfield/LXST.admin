<?php

namespace App\Http\Resources\Tenant\Admin;

use App\Http\Resources\Traits\HasActions;
use App\Models\User;
use App\Services\Authorisation\Enums\CorePermission;
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

        $actions = [];

        if ($request->user()?->can(CorePermission::ManageUsers, $user)) {
            $actions[] = Action::make('Edit', route('admin.users.edit', $user))
                ->icon('i-lucide-pencil');

            $actions[] = Action::make('Delete', route('admin.users.destroy', $user))
                ->icon('i-lucide-trash')
                ->method('DELETE')
                ->requireConfirmation("Are you sure you want to delete user $user->email?");
        } elseif ($request->user()?->can(CorePermission::ViewUsers)) {
            $actions[] = Action::make('View', route('admin.users.show', $user))
                ->icon('i-lucide-eye');
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
            'roles' => $this->whenLoaded(
                'roles',
                fn () => $user->roles->pluck('name'),
            ),
        ]);
    }
}
