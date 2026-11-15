<?php

namespace App\Http\Controllers\Tenant\Api\V1\Catalogue;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Api\V1\Catalogue\CatalogueResource;
use App\Models\Catalogue\Catalogue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Lets a website show its published catalogues (menus, price lists, products). Not cached, as which
 * apply now changes with the clock rather than with the content.
 */
class CatalogueController extends Controller
{
    /**
     * Every published catalogue with its contents, or only those that apply now with `?applies_now=1`,
     * e.g. to show whichever menu is being served
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $catalogues = Catalogue::query()->published()->with(CatalogueResource::RELATIONS)->get();

        if ($request->boolean('applies_now')) {
            $now = now();
            $catalogues = $catalogues->filter(fn (Catalogue $catalogue) => $catalogue->appliesAt($now))->values();
        }

        return CatalogueResource::collection($catalogues);
    }

    public function show(Catalogue $catalogue): CatalogueResource
    {
        abort_unless($catalogue->is_published, 404);

        return new CatalogueResource($catalogue->load(CatalogueResource::RELATIONS));
    }
}
