<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui';

// TODO: This should be dynamically generated based on the available settings pages
const links = [
    {
        label: 'Profile',
        icon: 'i-lucide-user',
        to: '/admin/settings/profile',
        exact: true,
    },
    {
        label: 'Security',
        icon: 'i-lucide-shield',
        to: '/admin/settings/security',
    },
] satisfies NavigationMenuItem[];
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar title="Settings">
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
            <div
                class="mx-auto flex w-full flex-col gap-4 sm:gap-6 lg:max-w-2xl lg:gap-12"
            >
                <slot />
            </div>
        </template>
    </UDashboardPanel>
</template>
