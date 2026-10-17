<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { SelectItem, TenantData } from '@/types';
import TenantForm from './components/TenantForm.vue';

const props = defineProps<{
    tenant: { data: TenantData };
    modules: SelectItem[];
}>();

const { trans } = useTrans();

const title = computed(() =>
    trans('tenants.edit.title', { name: props.tenant.data.name }),
);
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="title">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="title" />
            <div class="w-full max-w-4xl">
                <TenantForm :tenant="tenant.data" :modules="modules" />
            </div>
        </template>
    </UDashboardPanel>
</template>
