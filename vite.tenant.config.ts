import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';
import ui from '@nuxt/ui/vite';
import path from 'path';

export default defineConfig({
    cacheDir: 'node_modules/.vite-tenant',
    server: {
        port: 5173,
        strictPort: true,
    },
    build: {
        outDir: 'public/build-tenant',
    },
    plugins: [
        laravel({
            input: [
                'resources/js/tenant/app.ts',
                'resources/css/tenant/app.css'
            ],
            ssr: 'resources/js/tenant/ssr.ts',
            refresh: true,
            buildDirectory: 'build-tenant',
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        ui({
            inertia: true,
            components: {
                dirs: [
                    'resources/js/components',
                    'resources/js/tenant/components'
                ],
            },
            ui: {
                colors: {
                    primary: 'green',
                    neutral: 'neutral'
                }
            },
            autoImport: {
                vueTemplate: true,
                dirs: [
                    "resources/js/composables",
                    "resources/js/utils"
                ],
                imports: [
                    "vue",
                    "@vueuse/core",
                    {
                        "@inertiajs/vue3": ["router", "useForm", "usePage", "useRemember", "Head"],
                    },
                ],

            }
        })
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js/tenant'),
            '@shared': path.resolve(__dirname, './resources/js'),
        },
    },
});
