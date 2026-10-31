<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui';

const { trans } = useTrans();

// TODO: This should be dynamically generated based on the available settings pages
const links = [
    {
        label: trans('settings.profile.nav'),
        icon: 'i-lucide-user',
        to: '/admin/settings/profile',
        exact: true,
    },
    {
        label: trans('settings.security.nav'),
        icon: 'i-lucide-shield',
        to: '/admin/settings/security',
    },
] satisfies NavigationMenuItem[];
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('settings.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>

            <UDashboardToolbar>
                <!-- NOTE: The `-mx-1` class is used to align with the `DashboardSidebarCollapse` button here. -->
                <UNavigationMenu
                    :items="links"
                    highlight
                    class="-mx-1 flex-1"
                />
            </UDashboardToolbar>
        </template>

        <template #body>
            <div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
                <slot />
            </div>
        </template>
    </UDashboardPanel>
</template>
