<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CategoryForm from './components/CategoryForm.vue';

const { trans } = useTrans();

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
            <UDashboardNavbar
                :title="
                    trans('tracker.categories.edit.title', {
                        name: category.data.name,
                    })
                "
            >
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head
                :title="
                    trans('tracker.categories.edit.title', {
                        name: category.data.name,
                    })
                "
            />
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
