<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type {
    NestedTemplateOption,
    SelectItem,
    TemplateData,
    TemplateFieldTypeOption,
} from '@/types';
import TemplateEditor from './components/TemplateEditor.vue';

const props = defineProps<{
    template: { data: TemplateData };
    isInUse: boolean;
    types: SelectItem[];
    fieldTypes: TemplateFieldTypeOption[];
    nestedTemplates: NestedTemplateOption[];
}>();

const { trans } = useTrans();

const title = computed(() =>
    trans('cms.templates.edit.title', { name: props.template.data.name }),
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
                <TemplateEditor
                    :types="types"
                    :field-types="fieldTypes"
                    :nested-templates="nestedTemplates"
                    :template="template.data"
                    :is-in-use="isInUse"
                />
            </div>
        </template>
    </UDashboardPanel>
</template>
