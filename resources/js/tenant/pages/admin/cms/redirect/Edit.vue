<script setup lang="ts">
import type { RedirectData } from '@/types';
import RedirectForm from './components/RedirectForm.vue';

defineProps<{
    redirect: { data: RedirectData };
}>();
</script>

<template>
    <RedirectForm :redirect="redirect.data" />
</template>
