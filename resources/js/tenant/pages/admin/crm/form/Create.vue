<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { FormFieldTypeOption } from '@/types';
import CrmFormEditor from './components/CrmFormEditor.vue';

defineProps<{
    fieldTypes: FormFieldTypeOption[];
}>();

const { trans } = useTrans();
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('crm.forms.create.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('crm.forms.create.title')" />
            <div class="w-full max-w-4xl">
                <CrmFormEditor :field-types="fieldTypes" :create="true" />
            </div>
        </template>
    </UDashboardPanel>
</template>
