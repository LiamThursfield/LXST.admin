<?php

namespace App\Http\Middleware;

use App\Enums\AppContext;
use App\Models\Settings\GeneralSettings;
use App\Models\Settings\ThemeSettings;
use App\Services\FileManager\FileManagerStorage;
use App\Services\Localisation\FrontendTranslations;
use App\Services\Navigation\Constants\CentralMenu;
use App\Services\Navigation\Constants\TenantMenu;
use App\Services\Navigation\Exceptions\MenuKeyDoesNotExistException;
use App\Services\Navigation\NavigationService;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    /**
     * The session flash keys (set by controllers via `->with('success', ...)`) that are
     * passed to the frontend as Inertia flash data, so they are shown once as toasts.
     *
     * @var list<string>
     */
    protected array $sessionFlashKeys = ['success', 'error'];

    public function __construct(
        protected NavigationService $navigationService,
        protected FrontendTranslations $frontendTranslations,
    ) {}

    public function rootView(Request $request): string
    {
        // If a tenant is identified, use the tenant blade, otherwise central
        return tenant() ? 'tenant' : 'central';
    }

    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->shareSessionFlash($request);

        return parent::handle($request, $next);
    }

    /**
     * Pass any flashed session messages on as Inertia flash data. Unlike shared props, flash
     * data isn't kept in the browser history, so going back won't show the message again.
     */
    protected function shareSessionFlash(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $flash = array_filter($request->session()->only($this->sessionFlashKeys), fn ($value) => filled($value));

        if ($flash) {
            Inertia::flash($flash);
        }
    }

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     *
     * @throws MenuKeyDoesNotExistException
     */
    public function share(Request $request): array
    {
        $context = tenant() ? AppContext::Tenant : AppContext::Central;

        $mainMenuKey = $context == AppContext::Tenant ? TenantMenu::MENU_MAIN : CentralMenu::MENU_MAIN;

        $locale = app()->getLocale();

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
                'context' => $context,
            ],
            // The tenant's name and branding (site settings), used for page titles, the sidebar logo, favicon and colours
            'site' => $context === AppContext::Tenant ? fn () => $this->site() : null,
            'menus' => [
                'main' => $this->navigationService->resolveMenuForUser($mainMenuKey, $request->user()),
            ],
            'auth' => [
                'user' => $request->user(),
            ],
            'locale' => $locale,
            // Only sent on the first visit (or when the locale changes), as the strings rarely change
            'translations' => Inertia::once(fn () => $this->frontendTranslations->forLocale($locale))
                ->as("translations.{$locale}"),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array{name: string, logoUrl: string|null, faviconUrl: string|null, colors: array{primary: string, neutral: string}}
     */
    protected function site(): array
    {
        $general = app(GeneralSettings::class);
        $theme = app(ThemeSettings::class);
        $storage = app(FileManagerStorage::class);

        return [
            'name' => $general->site_name,
            'logoUrl' => $storage->urlFor($theme->logo_file_id),
            'faviconUrl' => $storage->urlFor($theme->favicon_file_id),
            'colors' => [
                'primary' => $theme->primary_color,
                'neutral' => $theme->neutral_color,
            ],
        ];
    }
}
