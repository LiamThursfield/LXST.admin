<?php

namespace App\Http\Controllers\Central\Admin;

use App\Http\Controllers\Controller;
use App\Http\Queries\Central\Admin\UserIndexQuery;
use App\Http\Requests\Central\Admin\User\UserIndexRequest;
use App\Http\Requests\Central\Admin\User\UserStoreRequest;
use App\Http\Requests\Central\Admin\User\UserUpdateRequest;
use App\Http\Resources\Central\Admin\UserResource;
use App\Models\User;
use App\Services\Central\CentralUserDeleter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The people who can log in to the central admin. They're all super users, so there are no roles,
 * and being logged in is enough to manage them.
 */
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
                ['accessorKey' => 'first_name', 'header' => __('users.columns.first_name')],
                ['accessorKey' => 'last_name', 'header' => __('users.columns.last_name')],
                ['accessorKey' => 'email', 'header' => __('users.columns.email')],
                ['accessorKey' => 'actions', 'header' => ''],
            ],
            'users' => UserResource::collection($query->handle($request)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/user/Create');
    }

    /**
     * The new user sets their own password, through the password setup email
     */
    public function store(UserStoreRequest $request): RedirectResponse
    {
        $user = User::create([
            ...$request->only('first_name', 'last_name', 'email'),
            'password' => Str::password(),
        ]);

        Password::sendResetLink(['email' => $user->email]);

        return redirect()->route('central.admin.users.index')
            ->with('success', __('users.flash.created', ['email' => $user->email]));
    }

    public function edit(User $user): Response
    {
        return Inertia::render('admin/user/Edit', [
            'user' => new UserResource($user),
        ]);
    }

    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $user->update($request->only('first_name', 'last_name', 'email'));

        return redirect()->route('central.admin.users.index')->with('success', __('users.flash.updated'));
    }

    public function destroy(Request $request, User $user, CentralUserDeleter $deleter): RedirectResponse
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => __('users.central.validation.delete_self')]);
        }

        $deleter->delete($user);

        return redirect()->back()->with('success', __('users.flash.deleted'));
    }
}
