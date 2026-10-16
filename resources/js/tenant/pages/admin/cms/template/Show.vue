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
    types: SelectItem[];
    fieldTypes: TemplateFieldTypeOption[];
    nestedTemplates: NestedTemplateOption[];
    organisationUnitTypes: SelectItem[];
}>();

const { trans } = useTrans();

const title = computed(() =>
    trans('cms.templates.show.title', { name: props.template.data.name }),
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
                    :organisation-unit-types="organisationUnitTypes"
                    :template="template.data"
                    readonly
                />
            </div>
        </template>
    </UDashboardPanel>
</template>
