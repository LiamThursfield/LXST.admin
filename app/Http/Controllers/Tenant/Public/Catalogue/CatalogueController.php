<?php

namespace App\Http\Controllers\Tenant\Public\Catalogue;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Api\V1\Catalogue\CatalogueResource;
use App\Models\Catalogue\Catalogue;
use App\Models\Settings\GeneralSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The hosted catalogue pages, for a link or a QR code (e.g. on a restaurant table) and to print or
 * save as a PDF. Staff who can view catalogues can also preview a draft.
 */
class CatalogueController extends Controller
{
    /**
     * Every published catalogue, those that apply now first
     */
    public function index(GeneralSettings $general): Response
    {
        $now = now();

        $catalogues = Catalogue::query()->published()->get()
            ->sortBy(fn (Catalogue $catalogue) => $catalogue->appliesAt($now) ? 0 : 1)
            ->map(fn (Catalogue $catalogue) => [
                'name' => $catalogue->name,
                'description' => $catalogue->description,
                'schedule' => $catalogue->scheduleSummary(),
                'applies_now' => $catalogue->appliesAt($now),
                'url' => $catalogue->publicUrl(),
            ])
            ->values()
            ->all();

        return Inertia::render('public/catalogue/Index', [
            'business' => $general->site_name,
            'catalogues' => $catalogues,
        ]);
    }

    public function show(Request $request, Catalogue $catalogue, GeneralSettings $general): Response
    {
        abort_unless($catalogue->is_published || ($request->user()?->can('view', $catalogue) ?? false), 404);

        return Inertia::render('public/catalogue/Show', [
            'business' => $general->site_name,
            'catalogue' => (new CatalogueResource($catalogue->load(CatalogueResource::RELATIONS)))->resolve($request),
            'isDraft' => ! $catalogue->is_published,
            // Opened from the admin's print button, so the print dialog opens straight away
            'print' => $request->boolean('print'),
            'indexUrl' => route('catalogue.public.index'),
        ]);
    }
}
