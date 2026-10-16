<?php

namespace App\Http\Controllers\Tenant\Admin\SiteSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\SiteSettings\IntegrationSettingsUpdateRequest;
use App\Models\Settings\IntegrationSettings;
use App\Services\Authorisation\Enums\SettingsPermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationSettingsController extends Controller
{
    public function edit(Request $request, IntegrationSettings $settings): Response
    {
        Gate::authorize(SettingsPermission::ViewSettings);

        return Inertia::render('admin/site-settings/Integrations', [
            'settings' => [
                'gtm_id' => $settings->gtm_id,
            ],
            'canManage' => $request->user()?->can(SettingsPermission::ManageSettings) ?? false,
        ]);
    }

    public function update(IntegrationSettingsUpdateRequest $request, IntegrationSettings $settings): RedirectResponse
    {
        $settings->gtm_id = $request->filled('gtm_id') ? $request->string('gtm_id')->toString() : null;
        $settings->save();

        return redirect()->route('admin.site-settings.integrations.edit')
            ->with('success', __('site_settings.integrations.updated'));
    }
}
