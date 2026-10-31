import path from 'path';
import ui from '@nuxt/ui/vite';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    cacheDir: 'node_modules/.vite-central',
    server: {
        port: 5174, // Unique port for Central
        strictPort: true,
    },
    build: {
        outDir: 'public/build-central',
        // The app chunk carries Nuxt UI's runtime for every page, so it sits above Vite's 500 kB default
        chunkSizeWarningLimit: 800,
        rollupOptions: {
            output: {
                // Vue and Inertia change less often than our code, so browsers keep them cached across deploys
                manualChunks: (id) => (/[\\/]node_modules[\\/](vue|@vue|@inertiajs)[\\/]/.test(id) ? 'vendor' : undefined),
            },
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/js/central/app.ts',
                'resources/css/central/app.css'
            ],
            ssr: 'resources/js/central/ssr.ts',
            refresh: true,
            buildDirectory: 'build-central',
        }),
        tailwindcss(),
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
                dts: 'components.central.d.ts',
                dirs: [
                    'resources/js/components',
                    'resources/js/central/components'
                ],
            },
            ui: {
                colors: {
                    primary: 'amber',
                    neutral: 'zinc'
                }
            },
            autoImport: {
                dts: 'auto-imports.central.d.ts',
                vueTemplate: true,
                // Components take a `readonly` prop, which Vue's `readonly()` would shadow in template types.
                ignore: ['readonly'],
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
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
});
