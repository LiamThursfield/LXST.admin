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
    isInUse: boolean;
    types: SelectItem[];
    fieldTypes: TemplateFieldTypeOption[];
    nestedTemplates: NestedTemplateOption[];
    organisationUnitTypes: SelectItem[];
}>();
</script>

<template>
    <TemplateEditor
        :template="template.data"
        :is-in-use="isInUse"
        :types="types"
        :field-types="fieldTypes"
        :nested-templates="nestedTemplates"
        :organisation-unit-types="organisationUnitTypes"
    />
</template>
