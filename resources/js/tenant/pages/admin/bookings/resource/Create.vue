<script setup lang="ts">
import type { BookableResourceFormOptions } from '@/types';
import ResourceForm from './components/ResourceForm.vue';

defineProps<BookableResourceFormOptions>();
</script>

<template>
    <ResourceForm
        :types="types"
        :has-locations="hasLocations"
        :locations="locations"
        :users="users"
        :services="services"
        :currency="currency"
        create
    />
</template>
