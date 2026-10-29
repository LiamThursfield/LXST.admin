<script setup lang="ts">
import CategoryForm from './components/CategoryForm.vue';

defineProps<{
    category: {
        data: {
            id: number;
            name: string;
            colour?: string | null;
            icon?: string | null;
            is_public: boolean;
        };
    };
    canManagePublic: boolean;
}>();
</script>

<template>
    <CategoryForm
        :category="category.data"
        :can-manage-public="canManagePublic"
        :readonly="false"
    />
</template>
