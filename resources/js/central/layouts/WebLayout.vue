<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { NavigationMenuItem } from '@nuxt/ui';
import { marketingModules, moduleIcons } from '@/lib/marketing';
import { home, login } from '@/routes/central';
import { dashboard } from '@/routes/central/admin';
import { show as moduleShow } from '@/routes/central/modules';

/**
 * The public site on the central domain
 */
const { trans } = useTrans();
const auth = useAuth();

const isSignedIn = computed(() => Boolean(auth.value.user));

const moduleLinks = computed(() =>
    marketingModules.map((module) => ({
        label: trans(`marketing.modules.${module}.name`),
        description: trans(`marketing.modules.${module}.summary`),
        icon: moduleIcons[module],
        to: moduleShow.url(module),
    })),
);

const navigation = computed<NavigationMenuItem[]>(() => [
    {
        label: trans('marketing.nav.modules'),
        children: moduleLinks.value,
    },
    {
        label: trans('marketing.nav.industries'),
        to: `${home.url()}#industries`,
    },
    {
        label: trans('marketing.nav.developers'),
        to: `${home.url()}#developers`,
    },
]);

const productLinks = computed(() => [
    {
        label: trans('marketing.nav.industries'),
        to: `${home.url()}#industries`,
    },
    {
        label: trans('marketing.nav.developers'),
        to: `${home.url()}#developers`,
    },
    {
        label: isSignedIn.value
            ? trans('marketing.nav.dashboard')
            : trans('marketing.nav.sign_in'),
        to: isSignedIn.value ? dashboard.url() : login.url(),
    },
]);
</script>

<template>
    <UApp>
        <UHeader :to="home.url()">
            <template #title>
                <span class="text-highlighted text-lg">
                    <span class="font-black">LXST</span
                    ><span class="font-semibold">.admin</span>
                </span>
            </template>

            <UNavigationMenu
                :items="navigation"
                variant="link"
                content-orientation="vertical"
                :ui="{ childLinkDescription: 'line-clamp-1' }"
            />

            <template #right>
                <UColorModeButton />

                <UButton
                    v-if="isSignedIn"
                    as="Link"
                    :href="dashboard.url()"
                    color="neutral"
                    variant="outline"
                    :label="trans('marketing.nav.dashboard')"
                    class="hidden sm:inline-flex"
                />
                <UButton
                    v-else
                    as="Link"
                    :href="login.url()"
                    color="neutral"
                    variant="outline"
                    :label="trans('marketing.nav.sign_in')"
                    class="hidden sm:inline-flex"
                />
            </template>

            <template #body>
                <UNavigationMenu
                    :items="navigation"
                    orientation="vertical"
                    class="-mx-2.5"
                />

                <UButton
                    as="Link"
                    :href="isSignedIn ? dashboard.url() : login.url()"
                    color="neutral"
                    variant="outline"
                    block
                    class="mt-6"
                    :label="
                        isSignedIn
                            ? trans('marketing.nav.dashboard')
                            : trans('marketing.nav.sign_in')
                    "
                />
            </template>
        </UHeader>

        <UMain>
            <slot />
        </UMain>

        <footer class="border-default border-t">
            <UContainer class="grid gap-10 py-14 sm:grid-cols-2 lg:grid-cols-4">
                <div class="lg:col-span-2">
                    <Link :href="home.url()" class="text-highlighted text-lg">
                        <span class="font-black">LXST</span
                        ><span class="font-semibold">.admin</span>
                    </Link>
                    <p class="text-muted mt-2 max-w-xs text-sm">
                        {{ trans('marketing.footer.tagline') }}
                    </p>
                </div>

                <nav :aria-label="trans('marketing.footer.modules')">
                    <p class="text-highlighted text-sm font-medium">
                        {{ trans('marketing.footer.modules') }}
                    </p>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li v-for="link in moduleLinks" :key="link.to">
                            <Link
                                :href="link.to"
                                class="text-muted hover:text-highlighted transition-colors"
                            >
                                {{ link.label }}
                            </Link>
                        </li>
                    </ul>
                </nav>

                <nav :aria-label="trans('marketing.footer.product')">
                    <p class="text-highlighted text-sm font-medium">
                        {{ trans('marketing.footer.product') }}
                    </p>
                    <ul class="mt-3 space-y-2 text-sm">
                        <li v-for="link in productLinks" :key="link.to">
                            <Link
                                :href="link.to"
                                class="text-muted hover:text-highlighted transition-colors"
                            >
                                {{ link.label }}
                            </Link>
                        </li>
                    </ul>
                </nav>
            </UContainer>

            <UContainer
                class="border-default text-muted flex items-center justify-between gap-4 border-t py-6 text-sm"
            >
                <p>
                    {{ trans('marketing.footer.built_by') }} ·
                    {{ new Date().getFullYear() }}
                </p>
                <UButton
                    to="https://github.com/LiamThursfield/LXST.admin"
                    target="_blank"
                    icon="simple-icons:github"
                    aria-label="GitHub"
                    color="neutral"
                    variant="ghost"
                    size="sm"
                />
            </UContainer>
        </footer>
    </UApp>
</template>
