<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { SelectItem, TaskDefinitionData } from '@/types';
import TaskDefinitionForm from './components/TaskDefinitionForm.vue';

defineProps<{
    task: { data: TaskDefinitionData };
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
            <UDashboardNavbar :title="`Task: ${task.data.title}`">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="`Task: ${task.data.title}`" />
            <div class="w-full max-w-4xl">
                <TaskDefinitionForm
                    :task="task.data"
                    :categories="categories"
                    :periods="periods"
                    :measures="measures"
                    :units="units"
                    :comparators="comparators"
                    :aggregations="aggregations"
                    :statuses="statuses"
                    :readonly="true"
                />
            </div>
        </template>
    </UDashboardPanel>
</template>
