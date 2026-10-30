<script setup lang="ts">
import type { SelectItem } from '@/types';
import TenantForm from './components/TenantForm.vue';

defineProps<{
    modules: SelectItem[];
}>();
</script>

<template>
    <TenantForm :modules="modules" />
</template>
