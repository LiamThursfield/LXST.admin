<script setup lang="ts">
import type { SelectItem } from '@/types';
import TaskDefinitionForm from './components/TaskDefinitionForm.vue';

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
</template>
