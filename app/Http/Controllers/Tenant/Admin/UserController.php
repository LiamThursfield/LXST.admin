<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\User\UserIndexQuery;
use App\Http\Requests\Tenant\Admin\User\UserIndexRequest;
use App\Http\Resources\Tenant\Admin\UserResource;
use App\Services\Authorisation\Enums\Role;
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
}
