<script setup lang="ts">
import type { ServiceSessionData, ServiceSessionFormOptions } from '@/types';
import SessionForm from './components/SessionForm.vue';

defineProps<
    ServiceSessionFormOptions & { session: { data: ServiceSessionData } }
>();
</script>

<template>
    <SessionForm
        :session="session.data"
        :services="services"
        :has-locations="hasLocations"
        :locations="locations"
        :resources="resources"
    />
</template>
