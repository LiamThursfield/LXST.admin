<?php

namespace App\Http\Controllers\Tenant\Admin\SiteSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\SiteSettings\GeneralSettingsUpdateRequest;
use App\Models\Settings\GeneralSettings;
use App\Services\Authorisation\Enums\SettingsPermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GeneralSettingsController extends Controller
{
    public function edit(Request $request, GeneralSettings $settings): Response
    {
        Gate::authorize(SettingsPermission::ViewSettings);

        return Inertia::render('admin/site-settings/General', [
            'settings' => [
                'site_name' => $settings->site_name,
            ],
            'canManage' => $request->user()?->can(SettingsPermission::ManageSettings) ?? false,
        ]);
    }

    public function update(GeneralSettingsUpdateRequest $request, GeneralSettings $settings): RedirectResponse
    {
        $settings->site_name = $request->string('site_name')->toString();
        $settings->save();

        return redirect()->route('admin.site-settings.general.edit')
            ->with('success', __('site_settings.general.updated'));
    }
}
