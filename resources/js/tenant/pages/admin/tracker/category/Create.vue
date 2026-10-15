<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CategoryForm from './components/CategoryForm.vue';

const { trans } = useTrans();

defineProps<{
    canManagePublic: boolean;
}>();
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('tracker.categories.create.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('tracker.categories.create.title')" />
            <div class="w-full max-w-4xl">
                <CategoryForm
                    :can-manage-public="canManagePublic"
                    :create="true"
                />
            </div>
        </template>
    </UDashboardPanel>
</template>
