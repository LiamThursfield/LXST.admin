<script setup lang="ts">
import type { CrmOptions } from '@/tenant/components/cms/nestedTemplates';
import type { SavedComponentOptions, TemplateData } from '@/types';
import PageEditor from './components/PageEditor.vue';

defineProps<{
    defaultParentId: number | null;
    templates: { data: TemplateData[] };
    nestedTemplates: { data: TemplateData[] };
    menuOptions: Array<{ value: number; label: string }>;
    crmOptions: CrmOptions | null;
    savedComponentOptions: SavedComponentOptions;
    layouts: Array<{ value: number; label: string }>;
    parents: Array<{ value: number; label: string }>;
}>();
</script>

<template>
    <PageEditor
        :templates="templates.data"
        :nested-templates="nestedTemplates.data"
        :menus="menuOptions"
        :crm-options="crmOptions"
        :saved-component-options="savedComponentOptions"
        :layouts="layouts"
        :parents="parents"
        :default-parent-id="defaultParentId"
    />
</template>
