<script setup lang="ts">
import type { ServiceData, ServiceFormOptions } from '@/types';
import ServiceForm from './components/ServiceForm.vue';

defineProps<ServiceFormOptions & { service: { data: ServiceData } }>();
</script>

<template>
    <ServiceForm
        :service="service.data"
        :booking-types="bookingTypes"
        :price-types="priceTypes"
        :hire-units="hireUnits"
        :resource-types="resourceTypes"
        :option-types="optionTypes"
        :categories="categories"
        :has-locations="hasLocations"
        :locations="locations"
        :currency="currency"
    />
</template>
