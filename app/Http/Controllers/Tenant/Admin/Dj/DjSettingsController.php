<?php

namespace App\Http\Controllers\Tenant\Admin\Dj;

use App\Enums\Dj\RequestsMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Dj\DjSettingsUpdateRequest;
use App\Models\Settings\DjSettings;
use App\Services\Authorisation\Enums\DjPermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DJ settings: gig defaults, the request page's address, and the request settings every gig
 * uses unless it overrides them
 */
class DjSettingsController extends Controller
{
    public function edit(Request $request, DjSettings $settings): Response
    {
        Gate::authorize(DjPermission::ViewGigs);

        return Inertia::render('admin/dj/settings/Edit', [
            'settings' => [
                'default_timezone' => $settings->default_timezone,
                'request_page_url' => $settings->request_page_url,
                'requests_mode' => $settings->requests_mode->value,
                'requests_open_minutes_before' => $settings->requests_open_minutes_before,
                'max_requests_per_visitor' => $settings->max_requests_per_visitor,
                'max_pending_requests' => $settings->max_pending_requests,
                'allow_custom_requests' => $settings->allow_custom_requests,
                'show_public_list' => $settings->show_public_list,
                'allow_upvotes' => $settings->allow_upvotes,
            ],
            'requestsModes' => RequestsMode::asSelectItems(),
            'canManage' => $request->user()?->can(DjPermission::ManageGigs) ?? false,
        ]);
    }

    public function update(DjSettingsUpdateRequest $request, DjSettings $settings): RedirectResponse
    {
        $settings->default_timezone = $request->string('default_timezone')->toString();
        $settings->request_page_url = $request->filled('request_page_url') ? $request->string('request_page_url')->toString() : null;
        $settings->requests_mode = RequestsMode::from($request->string('requests_mode')->toString());
        $settings->requests_open_minutes_before = $request->integer('requests_open_minutes_before');
        $settings->max_requests_per_visitor = $request->integer('max_requests_per_visitor');
        $settings->max_pending_requests = $request->integer('max_pending_requests');
        $settings->allow_custom_requests = $request->boolean('allow_custom_requests');
        $settings->show_public_list = $request->boolean('show_public_list');
        $settings->allow_upvotes = $request->boolean('allow_upvotes');
        $settings->save();

        return redirect()->route('admin.dj.settings.edit')->with('success', __('dj.settings.flash.updated'));
    }
}
