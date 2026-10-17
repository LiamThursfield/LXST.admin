<?php

namespace App\Http\Controllers\Central\Admin\Settings;

use App\Http\Controllers\Global\Admin\Settings\AbstractProfileController;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Models\User;
use App\Services\Central\CentralUserDeleter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ProfileController extends AbstractProfileController
{
    /**
     * Central users are deleted without spatie/laravel-permission's role clean up (see
     * CentralUserDeleter), and the last one can't delete themselves, or no one could log in
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $deleter = app(CentralUserDeleter::class);

        if ($deleter->isLastUser($user)) {
            throw ValidationException::withMessages(['password' => __('users.central.validation.last_user')]);
        }

        Auth::logout();

        $deleter->delete($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
