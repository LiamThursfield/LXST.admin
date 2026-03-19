<!-- This is adapted from: This is taken from: https://github.com/jkque/laravel-nuxt-ui-starter-kit/ -->
<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui';

const toast = useToast();

const open = ref(false);

const links = [
    {
        label: 'Home',
        icon: 'i-lucide-house',
        to: '/dashboard',
        onSelect: () => {
            open.value = false;
        },
    },
    {
        label: 'Settings',
        icon: 'i-lucide-settings',
        defaultOpen: true,
        type: 'trigger',
        children: [
            {
                label: 'Profile',
                to: '/settings/profile',
                exact: true,
                onSelect: () => {
                    open.value = false;
                },
            },
            {
                label: 'Security',
                to: '/settings/security',
                onSelect: () => {
                    open.value = false;
                },
            },
        ],
    },
] satisfies NavigationMenuItem[];

const groups = computed(() => [
    {
        id: 'links',
        label: 'Go to',
        items: links.flat(),
    },
]);

const cookie = useStorage('cookie-consent', 'pending');
if (cookie.value !== 'accepted') {
    toast.add({
        title: 'We use first-party cookies to enhance your experience on our website.',
        duration: 0,
        close: false,
        actions: [
            {
                label: 'Accept',
                color: 'neutral',
                variant: 'outline',
                onClick: () => {
                    cookie.value = 'accepted';
                },
            },
            {
                label: 'Opt out',
                color: 'neutral',
                variant: 'ghost',
            },
        ],
    });
}
</script>

<template>
    <Suspense>
        <UApp>
            <UDashboardGroup unit="rem" storage="local">
                <UDashboardSidebar
                    id="default"
                    v-model:open="open"
                    collapsible
                    resizable
                    class="bg-elevated/25"
                    :ui="{ footer: 'lg:border-t lg:border-default' }"
                >
                    <template #header="{ collapsed }">
                        <div
                            v-if="!collapsed"
                            class="mx-auto select-none text-lg"
                        >
                            <span class="text-primary font-bold">LXST</span
                            ><span>.admin</span>
                        </div>
                        <div v-else class="mx-auto select-none text-lg">
                            <div
                                class="bg-elevated text-primary flex h-8 w-8 items-center justify-center rounded font-bold"
                            >
                                L
                            </div>
                        </div>
                    </template>

                    <template #default="{ collapsed }">
                        <UDashboardSearchButton
                            :collapsed="collapsed"
                            class="ring-default bg-transparent"
                        />

                        <UNavigationMenu
                            :collapsed="collapsed"
                            :items="links"
                            orientation="vertical"
                            tooltip
                            popover
                        />
                    </template>

                    <template #footer="{ collapsed }">
                        <UserMenu :collapsed="collapsed" />
                    </template>
                </UDashboardSidebar>

                <UDashboardSearch :groups="groups" />

                <slot />

                <NotificationsSlideover />
            </UDashboardGroup>
        </UApp>
    </Suspense>
</template>
