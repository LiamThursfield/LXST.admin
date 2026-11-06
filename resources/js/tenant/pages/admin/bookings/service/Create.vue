<script setup lang="ts">
import type { ServiceFormOptions } from '@/types';
import ServiceForm from './components/ServiceForm.vue';

defineProps<ServiceFormOptions>();
</script>

<template>
    <ServiceForm
        :booking-types="bookingTypes"
        :price-types="priceTypes"
        :hire-units="hireUnits"
        :payment-requirements="paymentRequirements"
        :deposit-types="depositTypes"
        :resource-types="resourceTypes"
        :option-types="optionTypes"
        :categories="categories"
        :has-locations="hasLocations"
        :locations="locations"
        :currency="currency"
        create
    />
</template>
