<script setup lang="ts">
import type { MarketingModule } from '@/lib/marketing';
import BookingsMock from './BookingsMock.vue';
import CatalogueMock from './CatalogueMock.vue';
import CustomersMock from './CustomersMock.vue';
import DjMock from './DjMock.vue';
import TrackerMock from './TrackerMock.vue';
import WebsiteMock from './WebsiteMock.vue';

/**
 * The product mock-up for a module's page
 */
const props = defineProps<{ module: MarketingModule }>();

const mocks = {
    bookings: BookingsMock,
    website: WebsiteMock,
    customers: CustomersMock,
    catalogue: CatalogueMock,
    tracker: TrackerMock,
    dj: DjMock,
};

const mock = computed(() => mocks[props.module]);
</script>

<template>
    <component :is="mock" />
</template>
