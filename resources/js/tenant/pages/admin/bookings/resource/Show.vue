<script setup lang="ts">
import type {
    BookableResourceData,
    BookableResourceFormOptions,
} from '@/types';
import ResourceForm from './components/ResourceForm.vue';

defineProps<
    BookableResourceFormOptions & {
        bookableResource: { data: BookableResourceData };
    }
>();
</script>

<template>
    <ResourceForm
        :bookable-resource="bookableResource.data"
        :types="types"
        :has-locations="hasLocations"
        :locations="locations"
        :users="users"
        :services="services"
        :currency="currency"
        readonly
    />
</template>
