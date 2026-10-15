<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { CrmFormData, FormFieldTypeOption } from '@/types';
import CrmFormEditor from './components/CrmFormEditor.vue';

const props = defineProps<{
    form: { data: CrmFormData };
    fieldTypes: FormFieldTypeOption[];
}>();

const { trans } = useTrans();

const title = computed(() =>
    trans('crm.forms.show.title', { name: props.form.data.name }),
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
                <CrmFormEditor
                    :crm-form="form.data"
                    :field-types="fieldTypes"
                    :readonly="true"
                />
            </div>
        </template>
    </UDashboardPanel>
</template>
