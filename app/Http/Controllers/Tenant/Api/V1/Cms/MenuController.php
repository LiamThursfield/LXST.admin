<?php

namespace App\Http\Controllers\Tenant\Api\V1\Cms;

use App\Http\Controllers\Controller;
use App\Models\Cms\Menu;
use App\Services\Cms\Api\ApiResponseCache;
use App\Services\Cms\Api\ContentPresenter;
use Illuminate\Http\JsonResponse;

/**
 * A single menu, for sites that only need one rather than every menu from the site endpoint
 */
class MenuController extends Controller
{
    public function __construct(
        protected ContentPresenter $presenter,
        protected ApiResponseCache $cache,
    ) {}

    public function show(Menu $menu): JsonResponse
    {
        $cached = $this->cache->remember("menu|{$menu->id}", fn () => [
            'status' => 200,
            'body' => ['data' => $this->presenter->presentMenu($menu)],
        ]);

        return response()->json($cached['body'], $cached['status']);
    }
}
