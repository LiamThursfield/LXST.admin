<?php

namespace App\Http\Controllers\Tenant\Admin\SiteSettings;

use App\Enums\BookingType;
use App\Enums\TenantModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\SiteSettings\IndustrySettingsUpdateRequest;
use App\Models\Tenant;
use App\Services\Authorisation\Enums\SettingsPermission;
use App\Services\Industry\IndustryProfile;
use App\Services\Industry\IndustryProfiles;
use App\Services\Industry\Terminology;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What kind of business the tenant is, and the words and defaults it uses. Which modules it has
 * stays with the central admin, as those are what it pays for, so here they're only suggestions.
 */
class IndustrySettingsController extends Controller
{
    public function edit(Request $request, IndustryProfiles $profiles, Terminology $terminology): Response
    {
        Gate::authorize(SettingsPermission::ViewSettings);

        /** @var Tenant $tenant */
        $tenant = tenant();

        return Inertia::render('admin/site-settings/Industry', [
            'settings' => [
                'industry' => $tenant->industryProfile()->key,
                'terms' => $tenant->termOverrides(),
                'booking_types' => array_map(fn (BookingType $type) => $type->value, $tenant->bookingTypes()),
                'customer_fields' => $tenant->customerFields(),
            ],
            'industries' => array_map(fn (IndustryProfile $profile) => [
                ...$profile->toSelectItem(),
                'description' => $profile->description(),
                'terms' => $terminology->defaultsFor($profile->key),
                'booking_types' => array_map(fn (BookingType $type) => $type->value, $profile->bookingTypes),
                'customer_fields' => $profile->customerFields,
                'suggested_modules' => array_map(fn (TenantModule $module) => $module->value, $profile->suggestedModules),
            ], $profiles->all()),
            'termNames' => collect($terminology->termKeys())
                ->map(fn (string $key) => ['value' => $key, 'label' => (string) __("industries.term_names.{$key}")])
                ->all(),
            'bookingTypes' => BookingType::asSelectItems(),
            'customerFields' => $profiles->customerFieldItems(),
            'modules' => TenantModule::asSelectItems(),
            'activeModules' => $tenant->modules?->map(fn (TenantModule $module) => $module->value)->values()->all() ?? [],
            'canManage' => $request->user()?->can(SettingsPermission::ManageSettings) ?? false,
        ]);
    }

    public function update(IndustrySettingsUpdateRequest $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        $tenant->update([
            'industry' => $request->string('industry')->toString(),
            'industry_overrides' => $request->overrides() ?: null,
        ]);

        return redirect()->route('admin.site-settings.industry.edit')
            ->with('success', __('site_settings.industry.updated'));
    }
}
