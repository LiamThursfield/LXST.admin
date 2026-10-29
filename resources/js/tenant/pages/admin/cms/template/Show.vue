<script setup lang="ts">
import type {
    NestedTemplateOption,
    SelectItem,
    TemplateData,
    TemplateFieldTypeOption,
} from '@/types';
import TemplateEditor from './components/TemplateEditor.vue';

defineProps<{
    template: { data: TemplateData };
    types: SelectItem[];
    fieldTypes: TemplateFieldTypeOption[];
    nestedTemplates: NestedTemplateOption[];
    organisationUnitTypes: SelectItem[];
}>();
</script>

<template>
    <TemplateEditor
        :template="template.data"
        :types="types"
        :field-types="fieldTypes"
        :nested-templates="nestedTemplates"
        :organisation-unit-types="organisationUnitTypes"
        readonly
    />
</template>
