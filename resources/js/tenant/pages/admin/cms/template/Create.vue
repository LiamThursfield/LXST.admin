<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type {
    NestedTemplateOption,
    SelectItem,
    TemplateFieldTypeOption,
} from '@/types';
import TemplateEditor from './components/TemplateEditor.vue';

defineProps<{
    types: SelectItem[];
    fieldTypes: TemplateFieldTypeOption[];
    nestedTemplates: NestedTemplateOption[];
    organisationUnitTypes: SelectItem[];
}>();

const { trans } = useTrans();

const title = computed(() => trans('cms.templates.create.title'));
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
                />
            </div>
        </template>
    </UDashboardPanel>
</template>
