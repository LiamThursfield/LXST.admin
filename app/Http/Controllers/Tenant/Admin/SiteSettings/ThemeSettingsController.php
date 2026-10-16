<?php

namespace App\Http\Controllers\Tenant\Admin\SiteSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\SiteSettings\ThemeSettingsUpdateRequest;
use App\Models\Settings\ThemeSettings;
use App\Services\Authorisation\Enums\SettingsPermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ThemeSettingsController extends Controller
{
    public function edit(Request $request, ThemeSettings $settings): Response
    {
        Gate::authorize(SettingsPermission::ViewSettings);

        return Inertia::render('admin/site-settings/Theme', [
            'settings' => [
                'logo_file_id' => $settings->logo_file_id,
                'favicon_file_id' => $settings->favicon_file_id,
                'primary_color' => $settings->primary_color,
                'neutral_color' => $settings->neutral_color,
                'site_colors' => $settings->site_colors,
            ],
            'primaryPalettes' => ThemeSettings::PRIMARY_PALETTES,
            'neutralPalettes' => ThemeSettings::NEUTRAL_PALETTES,
            'defaultSiteColors' => ThemeSettings::DEFAULT_SITE_COLORS,
            'canManage' => $request->user()?->can(SettingsPermission::ManageSettings) ?? false,
        ]);
    }

    public function update(ThemeSettingsUpdateRequest $request, ThemeSettings $settings): RedirectResponse
    {
        $settings->logo_file_id = $request->filled('logo_file_id') ? $request->integer('logo_file_id') : null;
        $settings->favicon_file_id = $request->filled('favicon_file_id') ? $request->integer('favicon_file_id') : null;
        $settings->primary_color = $request->string('primary_color')->toString();
        $settings->neutral_color = $request->string('neutral_color')->toString();
        $settings->site_colors = $request->siteColors();
        $settings->save();

        return redirect()->route('admin.site-settings.theme.edit')
            ->with('success', __('site_settings.theme.updated'));
    }
}
