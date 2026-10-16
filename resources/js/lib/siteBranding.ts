import { router } from '@inertiajs/vue3';
import type { SiteBranding } from '@/types/app';

/** The favicon shown when the tenant hasn't chosen one (see resources/views/tenant.blade.php). */
const DEFAULT_FAVICON = '/favicon.svg';

let current: SiteBranding | null = null;

/** The tenant's site name, for page titles, or null before the first page has loaded. */
export function siteName(): string | null {
    return current?.name ?? null;
}

/**
 * Apply the tenant's colours and favicon now, and again after every visit, so saving the
 * theme takes effect straight away.
 */
export function syncSiteBranding(
    initial: SiteBranding | null | undefined,
): void {
    apply(initial ?? null);

    // `navigate` covers moving between pages; `success` also covers a form that redirects
    // back to the same page (e.g. saving the theme), which doesn't fire `navigate`
    const applyFromPage = (page: { props: Record<string, unknown> }) =>
        apply((page.props.site as SiteBranding | null) ?? null);

    router.on('navigate', (event) => applyFromPage(event.detail.page));
    router.on('success', (event) => applyFromPage(event.detail.page));
}

function apply(site: SiteBranding | null): void {
    current = site;

    if (!site) {
        return;
    }

    // Nuxt UI rebuilds its colour variables whenever the app config changes
    const appConfig = useAppConfig();
    appConfig.ui.colors.primary = site.colors.primary;
    appConfig.ui.colors.neutral = site.colors.neutral;

    setFavicon(site.faviconUrl ?? DEFAULT_FAVICON);
}

function setFavicon(href: string): void {
    if (typeof document === 'undefined') {
        return;
    }

    const icons =
        document.querySelectorAll<HTMLLinkElement>('link[rel="icon"]');

    // Already showing it, e.g. on the first page, which the server rendered with it
    if ([...icons].some((icon) => icon.getAttribute('href') === href)) {
        return;
    }

    icons.forEach((icon) => icon.remove());

    const icon = document.createElement('link');
    icon.rel = 'icon';
    icon.href = href;
    document.head.appendChild(icon);
}
