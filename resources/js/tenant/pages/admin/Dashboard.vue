<script setup lang="ts">
const { trans } = useTrans();
</script>

<template>
    <UDashboardPanel id="home">
        <template #header>
            <UDashboardNavbar
                :title="trans('common.dashboard.title')"
                :ui="{ right: 'gap-3' }"
            >
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>
    </UDashboardPanel>
</template>
>
