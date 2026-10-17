<?php

namespace App\Http\Controllers\Tenant\Api\V1;

use App\Enums\TenantModule;
use App\Http\Controllers\Controller;
use App\Models\Cms\Menu;
use App\Models\Settings\GeneralSettings;
use App\Models\Settings\IntegrationSettings;
use App\Models\Settings\ThemeSettings;
use App\Services\Cms\Api\ApiResponseCache;
use App\Services\Cms\Api\ContentPresenter;
use App\Services\FileManager\FileManagerStorage;
use Illuminate\Http\JsonResponse;

/**
 * The site-wide data a headless site needs on every page: its name, branding, theme, tracking
 * and menus. Menus are keyed by slug, and empty without the CMS module.
 */
class SiteController extends Controller
{
    public function __construct(
        protected ContentPresenter $presenter,
        protected FileManagerStorage $files,
        protected ApiResponseCache $cache,
    ) {}

    public function __invoke(): JsonResponse
    {
        $cached = $this->cache->remember('site', fn () => [
            'status' => 200,
            'body' => ['data' => $this->site()],
        ]);

        return response()->json($cached['body'], $cached['status']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function site(): array
    {
        $theme = app(ThemeSettings::class);

        return [
            'name' => app(GeneralSettings::class)->site_name,
            'logo_url' => $this->files->urlFor($theme->logo_file_id),
            'favicon_url' => $this->files->urlFor($theme->favicon_file_id),
            'theme' => [
                'primary' => $theme->primary_color,
                'neutral' => $theme->neutral_color,
                // Every site colour, null where the site should use its own
                'colors' => collect(ThemeSettings::DEFAULT_SITE_COLORS)
                    ->map(fn (string $default, string $key) => $theme->site_colors[$key] ?? null)
                    ->all(),
            ],
            'gtm_id' => app(IntegrationSettings::class)->gtm_id,
            'menus' => (object) $this->menus(),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function menus(): array
    {
        if (! tenant()?->hasModule(TenantModule::Cms)) {
            return [];
        }

        return Menu::query()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Menu $menu) => [$menu->slug => $this->presenter->presentMenu($menu)])
            ->all();
    }
}
