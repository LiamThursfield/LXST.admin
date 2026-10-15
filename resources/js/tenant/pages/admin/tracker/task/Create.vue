<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { SelectItem } from '@/types';
import TaskDefinitionForm from './components/TaskDefinitionForm.vue';

const { trans } = useTrans();

defineProps<{
    categories: SelectItem[];
    periods: SelectItem[];
    measures: SelectItem[];
    units: SelectItem[];
    comparators: SelectItem[];
    aggregations: SelectItem[];
    statuses: SelectItem[];
}>();
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('tracker.tasks.create.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('tracker.tasks.create.title')" />
            <div class="w-full max-w-4xl">
                <TaskDefinitionForm
                    :categories="categories"
                    :periods="periods"
                    :measures="measures"
                    :units="units"
                    :comparators="comparators"
                    :aggregations="aggregations"
                    :statuses="statuses"
                    :create="true"
                />
            </div>
        </template>
    </UDashboardPanel>
</template>
