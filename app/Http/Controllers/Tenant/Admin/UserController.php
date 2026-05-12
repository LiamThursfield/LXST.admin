<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\User\UserIndexQuery;
use App\Http\Requests\Tenant\Admin\User\UserIndexRequest;
use App\Http\Requests\Tenant\Admin\User\UserUpdateRequest;
use App\Http\Resources\Tenant\Admin\UserResource;
use App\Models\User;
use App\Services\Authorisation\Enums\CorePermission;
use App\Services\Authorisation\Enums\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(UserIndexRequest $request, UserIndexQuery $query): Response
    {
        return Inertia::render('admin/user/Index', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');
                if ($validated == null) {
                    return null;
                }

                return collect($validated)->filter()->toArray();
            },
            'columns' => [
                ['accessorKey' => 'first_name', 'header' => 'First Name'],
                ['accessorKey' => 'last_name', 'header' => 'Last Name'],
                ['accessorKey' => 'email', 'header' => 'Email'],
                ['accessorKey' => 'roles', 'header' => 'Roles'],
                ['accessorKey' => 'actions', 'header' => ''],
            ],
            'users' => UserResource::collection($query->handle($request)),
            'roles' => Role::asSelectItems(),
        ]);
    }

    public function show(User $user): Response
    {
        Gate::authorize(CorePermission::ViewUsers);

        return Inertia::render('admin/user/Show', [
            'user' => new UserResource($user->load('roles')),
            'roles' => Role::asSelectItems(),
        ]);
    }

    public function edit(User $user): Response
    {
        Gate::authorize(CorePermission::ManageUsers);

        return Inertia::render('admin/user/Edit', [
            'user' => new UserResource($user->load('roles')),
            'roles' => Role::asSelectItems(),
        ]);
    }

    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $user->update($request->only('first_name', 'last_name', 'email'));

        if ($request->has('roles')) {
            $user->syncRoles($request->validated('roles', []));
        }

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize(CorePermission::ManageUsers);

        $user->delete();

        return redirect()->back()->with('success', 'User deleted successfully.');
    }
}
