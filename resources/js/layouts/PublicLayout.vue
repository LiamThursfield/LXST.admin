<script lang="ts" setup>
import { Head } from '@inertiajs/vue3';
import colors from 'tailwindcss/colors';

/** The tenant's branding (site settings): name, logo and colours. */
const site = computed(() => usePage().props.site);

const shades = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

/**
 * The tenant's primary and neutral palettes, as the CSS variables Nuxt UI reads, so the hosted
 * pages look like the tenant's site rather than the admin
 */
const themeStyle = computed(() => {
    const palettes = colors as unknown as Record<
        string,
        Record<number, string>
    >;
    const style: Record<string, string> = {};

    for (const [name, palette] of Object.entries(site.value?.colors ?? {})) {
        const values = palette ? palettes[palette] : undefined;

        if (values) {
            for (const shade of shades) {
                style[`--ui-color-${name}-${shade}`] = values[shade];
            }
        }
    }

    return style;
});
</script>

<template>
    <UApp>
        <Head>
            <meta name="robots" content="noindex, nofollow" />
            <link v-if="site?.faviconUrl" rel="icon" :href="site.faviconUrl" />
        </Head>
        <div class="bg-default flex min-h-svh flex-col" :style="themeStyle">
            <header class="border-default border-b">
                <div
                    class="mx-auto flex w-full max-w-2xl items-center gap-3 px-4 py-3"
                >
                    <img
                        v-if="site?.logoUrl"
                        :src="site.logoUrl"
                        :alt="site.name"
                        class="h-9 w-auto"
                    />
                    <span v-else class="text-highlighted text-lg font-bold">{{
                        site?.name
                    }}</span>
                </div>
            </header>
            <main
                class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 px-4 py-6"
            >
                <slot />
            </main>
        </div>
    </UApp>
</template>
