<script setup lang="ts">
import type { SelectItem, TenantIndustryOption } from '@/types';
import TenantForm from './components/TenantForm.vue';

defineProps<{
    modules: SelectItem[];
    industries: TenantIndustryOption[];
}>();
</script>

<template>
    <TenantForm :modules="modules" :industries="industries" />
</template>
