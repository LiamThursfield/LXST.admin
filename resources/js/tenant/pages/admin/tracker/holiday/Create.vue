<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import HolidayForm from './components/HolidayForm.vue';

const { trans } = useTrans();
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('tracker.holidays.create.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('tracker.holidays.create.title')" />
            <div class="w-full max-w-4xl">
                <HolidayForm :create="true" />
            </div>
        </template>
    </UDashboardPanel>
</template>
