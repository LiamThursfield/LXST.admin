<?php

namespace App\Http\Controllers\Central\Admin;

use App\Http\Controllers\Controller;
use App\Http\Queries\Central\Admin\DashboardQuery;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(DashboardQuery $query): Response
    {
        return Inertia::render('admin/Dashboard', $query->handle());
    }
}
