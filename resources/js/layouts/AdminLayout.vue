<!-- This is adapted from: https://github.com/jkque/laravel-nuxt-ui-starter-kit/ -->
<script lang="ts" setup>
import { usePageMenus } from '@/composables/usePageMenus';

const toast = useToast();

const open = ref(false);

const mainMenu = usePageMenus().main;

// This is used for the DashboardSearch
// By default, we'll just add the main menu
const dashboardSearchGroups = computed(() => [
    {
        id: 'links',
        label: 'Go to',
        items: mainMenu.value.flat(),
    },
]) as any;

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
            <UDashboardGroup storage="local" unit="rem">
                <UDashboardSidebar
                    id="default"
                    v-model:open="open"
                    :ui="{ footer: 'lg:border-t lg:border-default' }"
                    class="bg-elevated/25"
                    collapsible
                    resizable
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
                                .a
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
                            :items="mainMenu"
                            orientation="vertical"
                            popover
                            tooltip
                        />
                    </template>

                    <template #footer="{ collapsed }">
                        <UserMenu :collapsed="collapsed" />
                    </template>
                </UDashboardSidebar>

                <UDashboardSearch :groups="dashboardSearchGroups" />

                <slot />

                <NotificationsSlideover />
            </UDashboardGroup>
        </UApp>
    </Suspense>
</template>
