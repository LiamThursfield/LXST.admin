<script setup lang="ts">
import type { ServiceSessionFormOptions } from '@/types';
import SessionForm from './components/SessionForm.vue';

defineProps<ServiceSessionFormOptions>();
</script>

<template>
    <SessionForm
        :services="services"
        :has-locations="hasLocations"
        :locations="locations"
        :resources="resources"
    />
</template>
