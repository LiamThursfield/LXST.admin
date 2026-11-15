<?php

namespace App\Http\Controllers\Global\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use Inertia\Inertia;
use Inertia\Response;

abstract class AbstractSecurityController extends Controller
{
    /**
     * Show the user's security settings page, including whether two-factor login is on.
     * Two-factor login that was turned on but never confirmed with a code is turned off again.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        $request->ensureStateIsValid();

        return Inertia::render('admin/settings/Security', [
            'twoFactorEnabled' => $request->user()?->hasEnabledTwoFactorAuthentication() ?? false,
        ]);
    }
}
