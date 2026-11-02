<script setup lang="ts">
import type {
    NestedTemplateOption,
    SelectItem,
    TemplateFieldTypeOption,
    TemplateType,
} from '@/types';
import TemplateEditor from './components/TemplateEditor.vue';

defineProps<{
    types: SelectItem[];
    fieldTypes: TemplateFieldTypeOption[];
    nestedTemplates: NestedTemplateOption[];
    organisationUnitTypes: SelectItem[];
    initialType: TemplateType | null;
}>();
</script>

<template>
    <TemplateEditor
        :types="types"
        :field-types="fieldTypes"
        :nested-templates="nestedTemplates"
        :organisation-unit-types="organisationUnitTypes"
        :initial-type="initialType"
    />
</template>
