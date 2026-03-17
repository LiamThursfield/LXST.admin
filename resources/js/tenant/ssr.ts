import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import ui from '@nuxt/ui/vue-plugin';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import AdminLayout from '@/layouts/AdminLayout.vue';

const appName = import.meta.env.VITE_APP_NAME || 'LXST.admin';

createServer(
    (page) =>
        createInertiaApp({
            page,
            render: renderToString,
            title: (title) => (title ? `${title} - ${appName}` : appName),
            resolve: async (name) => {
                const page = (await resolvePageComponent(
                    `./pages/${name}.vue`,
                    import.meta.glob<DefineComponent>('./pages/**/*.vue'), // Scoped to tenant pages
                )) as any;

                // As the tenant does not currently have any pages outside admin and auth,
                // let's set the default layout to the admin layout
                page.default.layout = page.default.layout || AdminLayout;

                return page;
            },
            setup: ({ App, props, plugin }) =>
                createSSRApp({ render: () => h(App, props) })
                    .use(plugin)
                    .use(ui), // Ensures styles/icons render correctly on initial load
        }),
    { cluster: true },
);
