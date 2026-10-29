<script setup lang="ts">
import type { SelectItem, TenantData } from '@/types';
import TenantForm from './components/TenantForm.vue';

defineProps<{
    tenant: { data: TenantData };
    modules: SelectItem[];
}>();
</script>

<template>
    <TenantForm :tenant="tenant.data" :modules="modules" />
</template>
