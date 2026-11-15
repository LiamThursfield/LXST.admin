<?php

namespace App\Http\Controllers\Tenant\Admin\SiteSettings;

use App\Enums\CaptchaProvider;
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
            // The captcha secret key is never sent to the browser, only whether one is saved
            'settings' => [
                'gtm_id' => $settings->gtm_id,
                'captcha_provider' => $settings->captcha_provider?->value,
                'captcha_site_key' => $settings->captcha_site_key,
            ],
            'hasCaptchaSecret' => filled($settings->captcha_secret_key),
            'captchaProviders' => CaptchaProvider::asSelectItems(),
            'canManage' => $request->user()?->can(SettingsPermission::ManageSettings) ?? false,
        ]);
    }

    public function update(IntegrationSettingsUpdateRequest $request, IntegrationSettings $settings): RedirectResponse
    {
        $settings->fill($request->settingsAttributes($settings));
        $settings->save();

        return redirect()->route('admin.site-settings.integrations.edit')
            ->with('success', __('site_settings.integrations.updated'));
    }
}
