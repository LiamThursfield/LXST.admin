<?php

namespace App\Http\Controllers\Tenant\Admin\SiteSettings;

use App\Http\Controllers\Controller;
use App\Services\Authorisation\Enums\SettingsPermission;
use App\Services\Mail\EmailPreviews;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Previews of the emails the site sends, with sample data, so they can be checked without
 * sending them
 */
class EmailPreviewController extends Controller
{
    public function index(EmailPreviews $previews): Response
    {
        Gate::authorize(SettingsPermission::ViewSettings);

        return Inertia::render('admin/site-settings/EmailPreviews', [
            'groups' => $previews->all()
                ->map(fn (array $preview, string $key) => [
                    'key' => $key,
                    'group' => $preview['group'],
                    'name' => $preview['name'],
                    'url' => route('admin.site-settings.email-previews.show', $key),
                ])
                ->groupBy('group')
                ->map(fn ($emails, string $group) => ['label' => $group, 'emails' => $emails->values()->all()])
                ->values()
                ->all(),
        ]);
    }

    /**
     * The rendered email, shown in the preview's iframe
     */
    public function show(string $preview, EmailPreviews $previews): HttpResponse
    {
        Gate::authorize(SettingsPermission::ViewSettings);

        $email = $previews->find($preview);

        abort_if($email === null, HttpResponse::HTTP_NOT_FOUND);

        return response($email['render']()->render());
    }
}
