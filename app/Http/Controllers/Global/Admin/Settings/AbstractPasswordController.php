<?php

namespace App\Http\Controllers\Global\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use Illuminate\Http\RedirectResponse;

abstract class AbstractPasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()?->update([
            'password' => $request->password,
        ]);

        return back();
    }
}
