<?php

namespace App\Http\Controllers\Tenant\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Settings\TimezoneUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class TimezoneController extends Controller
{
    /**
     * Default the user's timezone from their browser. Only fills an unset
     * timezone, so a choice made on the profile page is never overwritten.
     */
    public function __invoke(TimezoneUpdateRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->getAttribute('timezone') === null) {
            $user->update(['timezone' => $request->string('timezone')->value()]);
        }

        return redirect()->back();
    }
}
