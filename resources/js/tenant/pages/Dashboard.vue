<script setup lang="ts"></script>

<template>
    <UDashboardPanel id="home">
        <template #header>
            <UDashboardNavbar title="Home" :ui="{ right: 'gap-3' }">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>
    </UDashboardPanel>
</template>
>
