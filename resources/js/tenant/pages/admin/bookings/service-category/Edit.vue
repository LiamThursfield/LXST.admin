<script setup lang="ts">
import type { ServiceCategoryData } from '@/types';
import CategoryForm from './components/CategoryForm.vue';

defineProps<{
    category: { data: ServiceCategoryData };
}>();
</script>

<template>
    <CategoryForm :category="category.data" />
</template>
