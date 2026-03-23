import '../../css/central/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import ui from '@nuxt/ui/vue-plugin';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

const appName = import.meta.env.VITE_APP_NAME || 'LXST.admin (CENTRAL)';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
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
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ui) // This applies the Nuxt UI theme defined i vite.central.config.ts
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
