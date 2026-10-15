<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import HolidayForm from './components/HolidayForm.vue';

defineProps<{
    holiday: {
        data: {
            id: number;
            label?: string | null;
            starts_on: string;
            ends_on: string;
        };
    };
}>();
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar title="Edit Holiday">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head title="Edit Holiday" />
            <div class="w-full max-w-4xl">
                <HolidayForm :holiday="holiday.data" :readonly="false" />
            </div>
        </template>
    </UDashboardPanel>
</template>
