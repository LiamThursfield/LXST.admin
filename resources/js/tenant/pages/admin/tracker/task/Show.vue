<script setup lang="ts">
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
</template>
