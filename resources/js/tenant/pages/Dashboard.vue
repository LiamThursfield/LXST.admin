<script setup lang="ts">
const { isNotificationsSlideoverOpen } = useDashboard();
</script>

<template>
    <UDashboardPanel id="home">
        <template #header>
            <UDashboardNavbar title="Home" :ui="{ right: 'gap-3' }">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>

                <template #right>
                    <UTooltip text="Notifications" :shortcuts="['N']">
                        <UButton
                            color="neutral"
                            variant="ghost"
                            square
                            @click="isNotificationsSlideoverOpen = true"
                        >
                            <UChip color="error" inset>
                                <UIcon
                                    name="i-lucide-bell"
                                    class="size-5 shrink-0"
                                />
                            </UChip>
                        </UButton>
                    </UTooltip>
                </template>
            </UDashboardNavbar>
        </template>
    </UDashboardPanel>
</template>
>
