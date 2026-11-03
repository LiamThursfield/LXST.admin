<script setup lang="ts">
import type { CrmOptions } from '@/tenant/components/cms/nestedTemplates';
import type { SavedComponentOptions, TemplateData } from '@/types';
import LayoutEditor from './components/LayoutEditor.vue';

defineProps<{
    templates: { data: TemplateData[] };
    nestedTemplates: { data: TemplateData[] };
    menuOptions: Array<{ value: number; label: string }>;
    crmOptions: CrmOptions | null;
    savedComponentOptions: SavedComponentOptions;
}>();
</script>

<template>
    <LayoutEditor
        :templates="templates.data"
        :nested-templates="nestedTemplates.data"
        :menus="menuOptions"
        :crm-options="crmOptions"
        :saved-component-options="savedComponentOptions"
    />
</template>
