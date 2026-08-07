<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CategoryForm from './components/CategoryForm.vue';

defineProps<{
    canManagePublic: boolean;
}>();
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar title="Create Category">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head title="Create Category" />
            <div class="w-full max-w-4xl">
                <CategoryForm
                    :can-manage-public="canManagePublic"
                    :create="true"
                />
            </div>
        </template>
    </UDashboardPanel>
</template>
