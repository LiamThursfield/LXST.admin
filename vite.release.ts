import { loadEnv, type Plugin } from 'vite';

/**
 * `pnpm build:release` builds the assets that are committed and deployed (`--mode release`).
 *
 * Central routes are tied to the central domain, so Wayfinder writes that domain into their URLs
 * (e.g. '//admin.example.com/admin/users'). A normal build takes it from the local APP_URL; a
 * release build takes it from PRODUCTION_APP_URL in .env, and the central build fails if its
 * links still point at the local domain.
 */
const RELEASE_MODE = 'release';

/**
 * The production APP_URL for a release build; null for any other build
 */
function productionAppUrl(mode: string): URL | null {
    if (mode !== RELEASE_MODE) {
        return null;
    }

    const value = loadEnv(mode, process.cwd(), '').PRODUCTION_APP_URL;

    if (!value) {
        throw new Error(
            "Add PRODUCTION_APP_URL to .env before a release build: the central admin's URL, e.g. https://admin.example.com",
        );
    }

    return new URL(value);
}

/**
 * For a release build, the command that generates Wayfinder's routes with the production APP_URL.
 * Undefined otherwise, so the plugin uses its own command.
 */
export function releaseWayfinderCommand(mode: string): string | undefined {
    const appUrl = productionAppUrl(mode);

    return appUrl
        ? `APP_URL='${appUrl.origin}' php artisan wayfinder:generate`
        : undefined;
}

/**
 * For a release build, a check that fails the central build unless its links are to the
 * production central domain, and not the local one
 */
export function releaseCentralLinkCheck(mode: string): Plugin[] {
    const appUrl = productionAppUrl(mode);

    if (!appUrl) {
        return [];
    }

    const localAppUrl = loadEnv(mode, process.cwd(), '').APP_URL;
    const productionLink = `//${appUrl.hostname}/`;
    const localLink = localAppUrl
        ? `//${new URL(localAppUrl).hostname}/`
        : null;

    return [
        {
            name: 'lxst:release-central-links',
            apply: 'build',
            generateBundle(_options, bundle) {
                const code = Object.values(bundle)
                    .map((file) => (file.type === 'chunk' ? file.code : ''))
                    .join('\n');

                if (!code.includes(productionLink)) {
                    this.error(
                        `The central build has no links to ${productionLink}. Build the tenant app first (pnpm build:release does), so Wayfinder's routes use PRODUCTION_APP_URL.`,
                    );
                }

                if (
                    localLink &&
                    localLink !== productionLink &&
                    code.includes(localLink)
                ) {
                    this.error(
                        `The central build still links to ${localLink}. Build the tenant app first (pnpm build:release does), so Wayfinder's routes use PRODUCTION_APP_URL.`,
                    );
                }
            },
        },
    ];
}
