<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import HolidayForm from './components/HolidayForm.vue';
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar title="Create Holiday">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head title="Create Holiday" />
            <div class="w-full max-w-4xl">
                <HolidayForm :create="true" />
            </div>
        </template>
    </UDashboardPanel>
</template>
