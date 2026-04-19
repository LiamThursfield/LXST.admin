<?php

namespace App\Http\Controllers\Global\Admin\Settings;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

abstract class AbstractSecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('admin/settings/Security');
    }
}
