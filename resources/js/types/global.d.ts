import type { AriaAttributes } from 'vue';
import type { NewApiToken } from '@/types/admin';
import type { App, SiteBranding } from '@/types/app';
import type { Auth } from '@/types/auth';
import type { Translations } from '@/types/localisation';
import type NavigationMenu from '@/types/navigation';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        flashDataType: {
            success?: string;
            error?: string;
            /** Only flashed once, straight after the token is created */
            apiToken?: NewApiToken;
        };
        sharedPageProps: {
            app: App;
            auth: Auth;
            locale: string;
            menus: {
                [key: string]: NavigationMenu;
            };
            /** Only shared in the tenant app. */
            site: SiteBranding | null;
            sidebarOpen: boolean;
            translations: Translations;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    /**
     * Accessibility attributes fall through to a component's root element, so allow them on any
     * component under `strictTemplates`.
     */
    interface AllowedComponentProps extends AriaAttributes {
        tabindex?: number | string;
    }

    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
