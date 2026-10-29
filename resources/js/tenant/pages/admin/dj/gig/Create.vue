<script setup lang="ts">
import GigForm from '@/tenant/pages/admin/dj/gig/components/GigForm.vue';
import type { RequestDefaults, SelectItem } from '@/types';

defineProps<{
    defaultTimezone: string;
    defaults: RequestDefaults;
    requestsModes: SelectItem[];
}>();
</script>

<template>
    <GigForm
        :default-timezone="defaultTimezone"
        :defaults="defaults"
        :requests-modes="requestsModes"
    />
</template>
