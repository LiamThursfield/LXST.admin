<?php

namespace App\Http\Controllers\Tenant\Admin\SiteSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\SiteSettings\MailSettingsUpdateRequest;
use App\Http\Requests\Tenant\Admin\SiteSettings\MailTestRequest;
use App\Mail\Settings\MailSettingsTestMail;
use App\Models\Settings\GeneralSettings;
use App\Models\Settings\MailSettings;
use App\Services\Authorisation\Enums\SettingsPermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class MailSettingsController extends Controller
{
    public function edit(Request $request, MailSettings $settings): Response
    {
        Gate::authorize(SettingsPermission::ViewSettings);

        // Secrets are never sent back to the browser, only whether one is saved
        $values = collect($settings->toArray())->except(MailSettings::SECRETS)->all();
        $savedSecrets = collect(MailSettings::SECRETS)
            ->mapWithKeys(fn (string $secret) => [$secret => filled($settings->{$secret})])
            ->all();

        return Inertia::render('admin/site-settings/Mail', [
            'settings' => $values,
            'savedSecrets' => $savedSecrets,
            'drivers' => MailSettings::DRIVERS,
            'mailgunEndpoints' => MailSettings::MAILGUN_ENDPOINTS,
            'defaultFrom' => [
                'address' => config('mail.from.address'),
                'name' => config('mail.from.name'),
            ],
            'canManage' => $request->user()?->can(SettingsPermission::ManageSettings) ?? false,
        ]);
    }

    public function update(MailSettingsUpdateRequest $request, MailSettings $settings): RedirectResponse
    {
        $settings->fill($request->settingsAttributes($settings));
        $settings->save();

        return redirect()->route('admin.site-settings.mail.edit')
            ->with('success', __('site_settings.mail.updated'));
    }

    /**
     * Sends a test email straight away (not queued) with the saved settings, so any error can be shown
     */
    public function test(MailTestRequest $request, MailSettings $settings, GeneralSettings $general): RedirectResponse
    {
        $mailerName = $settings->driver
            ? __("site_settings.mail.drivers.{$settings->driver}")
            : __('site_settings.mail.drivers.default');

        try {
            Mail::to($request->string('to')->toString())
                ->send(new MailSettingsTestMail($general->site_name, $mailerName));
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', __('site_settings.mail.test_failed', [
                'error' => Str::limit($exception->getMessage(), 300),
            ]));
        }

        return back()->with('success', __('site_settings.mail.test_sent', ['to' => $request->string('to')]));
    }
}
