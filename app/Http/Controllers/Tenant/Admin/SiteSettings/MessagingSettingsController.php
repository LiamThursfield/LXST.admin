<?php

namespace App\Http\Controllers\Tenant\Admin\SiteSettings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\SiteSettings\MessagingSettingsUpdateRequest;
use App\Http\Requests\Tenant\Admin\SiteSettings\MessagingTestRequest;
use App\Models\Settings\GeneralSettings;
use App\Models\Settings\MessagingSettings;
use App\Services\Authorisation\Enums\SettingsPermission;
use App\Services\Messaging\MessagingException;
use App\Services\Messaging\PhoneNumber;
use App\Services\Messaging\TwilioClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The tenant's own Twilio account, for texts and WhatsApp messages about bookings
 */
class MessagingSettingsController extends Controller
{
    public function edit(Request $request, MessagingSettings $settings): Response
    {
        Gate::authorize(SettingsPermission::ViewSettings);

        return Inertia::render('admin/site-settings/Messaging', [
            // The token is never sent back to the browser, only whether one is saved
            'settings' => collect($settings->toArray())->except(MessagingSettings::SECRETS)->all(),
            'hasToken' => filled($settings->twilio_auth_token),
            'canManage' => $request->user()?->can(SettingsPermission::ManageSettings) ?? false,
        ]);
    }

    public function update(MessagingSettingsUpdateRequest $request, MessagingSettings $settings): RedirectResponse
    {
        $settings->fill($request->settingsAttributes($settings));
        $settings->save();

        return redirect()->route('admin.site-settings.messaging.edit')
            ->with('success', __('site_settings.messaging.updated'));
    }

    /**
     * Sends a test text straight away with the saved settings, so any error can be shown
     */
    public function test(MessagingTestRequest $request, MessagingSettings $settings, TwilioClient $twilio, GeneralSettings $general): RedirectResponse
    {
        if (! $settings->canSendSms()) {
            return back()->with('error', __('site_settings.messaging.test_not_set_up'));
        }

        $to = (string) PhoneNumber::international($request->string('to')->toString());

        try {
            $twilio->sendSms($to, __('site_settings.messaging.test_body', ['site' => $general->site_name]));
        } catch (MessagingException $exception) {
            return back()->with('error', __('site_settings.messaging.test_failed', ['error' => Str::limit($exception->getMessage(), 300)]));
        }

        return back()->with('success', __('site_settings.messaging.test_sent', ['to' => $to]));
    }
}
