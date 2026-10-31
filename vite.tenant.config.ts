import path from 'path';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import ui from '@nuxt/ui/vite';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    cacheDir: 'node_modules/.vite-tenant',
    server: {
        port: 5173,
        strictPort: true,
    },
    build: {
        outDir: 'public/build-tenant',
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
                dts: 'components.tenant.d.ts',
                dirs: [
                    'resources/js/components',
                    'resources/js/tenant/components'
                ],
            },
            ui: {
                colors: {
                    primary: 'amber',
                    neutral: 'zinc'
                },
                input: {
                    variants: {
                        size: {
                            md: {
                                base: 'px-2.5 py-1.5 text-base gap-1.5',
                            }
                        }
                    }
                }
            },
            autoImport: {
                dts: 'auto-imports.tenant.d.ts',
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
