<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { SelectItem } from '@/types';
import TenantForm from './components/TenantForm.vue';

defineProps<{
    modules: SelectItem[];
}>();

const { trans } = useTrans();
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('tenants.create.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('tenants.create.title')" />
            <div class="w-full max-w-4xl">
                <TenantForm :modules="modules" />
            </div>
        </template>
    </UDashboardPanel>
</template>
