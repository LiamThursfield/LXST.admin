<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\User\UserIndexQuery;
use App\Http\Requests\Tenant\Admin\User\UserIndexRequest;
use App\Http\Resources\Tenant\Admin\UserResource;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(UserIndexRequest $request, UserIndexQuery $query): Response
    {
        return Inertia::render('admin/user/Index', [
            'users' => UserResource::collection($query->handle($request)),
        ]);
    }
}
