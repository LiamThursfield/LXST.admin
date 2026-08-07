<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
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
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="`Edit Category: ${category.data.name}`">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="`Edit Category: ${category.data.name}`" />
            <div class="w-full max-w-4xl">
                <CategoryForm
                    :category="category.data"
                    :can-manage-public="canManagePublic"
                    :readonly="false"
                />
            </div>
        </template>
    </UDashboardPanel>
</template>
