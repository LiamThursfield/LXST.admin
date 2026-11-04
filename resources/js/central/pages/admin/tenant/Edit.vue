<script setup lang="ts">
import type { SelectItem, TenantData, TenantIndustryOption } from '@/types';
import TenantForm from './components/TenantForm.vue';

defineProps<{
    tenant: { data: TenantData };
    modules: SelectItem[];
    industries: TenantIndustryOption[];
}>();
</script>

<template>
    <TenantForm
        :tenant="tenant.data"
        :modules="modules"
        :industries="industries"
    />
</template>
