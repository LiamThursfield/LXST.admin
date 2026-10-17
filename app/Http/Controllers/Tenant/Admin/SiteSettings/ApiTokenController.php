<?php

namespace App\Http\Controllers\Tenant\Admin\SiteSettings;

use App\Enums\Api\ApiTokenAbility;
use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\SiteSettings\ApiTokenIndexQuery;
use App\Http\Requests\Tenant\Admin\SiteSettings\ApiTokenIndexRequest;
use App\Http\Requests\Tenant\Admin\SiteSettings\ApiTokenStoreRequest;
use App\Http\Resources\Tenant\Admin\SiteSettings\ApiTokenResource;
use App\Models\ApiToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tokens that let trusted clients, e.g. the Rekordbox CLI tool, call the tenant API
 */
class ApiTokenController extends Controller
{
    public function index(ApiTokenIndexRequest $request, ApiTokenIndexQuery $query): Response
    {
        return Inertia::render('admin/site-settings/ApiTokens', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');
                if ($validated == null) {
                    return null;
                }

                return collect($validated)->filter()->toArray();
            },
            'columns' => [
                ['accessorKey' => 'name', 'header' => __('site_settings.api_tokens.columns.name')],
                ['accessorKey' => 'owner', 'header' => __('site_settings.api_tokens.columns.owner')],
                ['accessorKey' => 'last_used_at', 'header' => __('site_settings.api_tokens.columns.last_used')],
                ['accessorKey' => 'expires_at', 'header' => __('site_settings.api_tokens.columns.expires')],
                ['accessorKey' => 'actions', 'header' => ''],
            ],
            'tokens' => ApiTokenResource::collection($query->handle($request)),
            'abilities' => ApiTokenAbility::asSelectItems(),
            'canCreate' => $request->user()?->can('create', ApiToken::class) ?? false,
        ]);
    }

    /**
     * The plain token is flashed so it's shown once; only its hash is stored
     */
    public function store(ApiTokenStoreRequest $request): RedirectResponse
    {
        /** @var string $name */
        $name = $request->validated('name');

        $newToken = $request->creator()->createToken($name, $request->abilities(), $request->expiresAt());

        Inertia::flash('apiToken', [
            'name' => $name,
            'token' => $newToken->plainTextToken,
        ]);

        return redirect()->route('admin.site-settings.api-tokens.index')
            ->with('success', __('site_settings.api_tokens.flash.created'));
    }

    public function destroy(ApiToken $apiToken): RedirectResponse
    {
        Gate::authorize('delete', $apiToken);

        $apiToken->delete();

        return redirect()->back()->with('success', __('site_settings.api_tokens.flash.revoked'));
    }
}
