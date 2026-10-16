import '../../css/tenant/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import ui from '@nuxt/ui/vue-plugin';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { siteName, syncSiteBranding } from '@/lib/siteBranding';
import { syncBrowserTimezone } from '@/lib/timezone';
import type { SiteBranding } from '@/types/app';

const appName = import.meta.env.VITE_APP_NAME || 'LXST.admin';

createInertiaApp({
    // Pages are titled with the tenant's site name (site settings), falling back to the app's name
    title: (title) => {
        const name = siteName() ?? appName;

        return title ? `${title} - ${name}` : name;
    },
    resolve: async (name) => {
        const page = (await resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'), // Only finds pages in resources/js/tenant/pages
        )) as any;

        // As the tenant does not currently have any pages outside admin and auth,
        // let's set the default layout to the admin layout
        page.default.layout = page.default.layout || AdminLayout;

        return page;
    },
    setup({ el, App, props, plugin }) {
        syncSiteBranding(props.initialPage.props.site as SiteBranding | null);

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ui) // This applies the Nuxt UI theme defined i vite.tenant.config.ts
            .mount(el);

        syncBrowserTimezone();
    },
    progress: {
        color: '#4B5563',
    },
});
