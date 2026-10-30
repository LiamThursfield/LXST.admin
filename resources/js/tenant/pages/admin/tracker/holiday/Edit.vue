<script setup lang="ts">
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
    <HolidayForm :holiday="holiday.data" :readonly="false" />
</template>
