<script setup lang="ts">
import CategoryForm from './components/CategoryForm.vue';

defineProps<{
    canManagePublic: boolean;
}>();
</script>

<template>
    <CategoryForm :can-manage-public="canManagePublic" :create="true" />
</template>
