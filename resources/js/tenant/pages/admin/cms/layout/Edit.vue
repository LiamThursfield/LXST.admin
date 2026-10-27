<script setup lang="ts">
import type { CrmOptions } from '@/tenant/components/cms/nestedTemplates';
import type { LayoutData, TemplateData } from '@/types';
import LayoutEditor from './components/LayoutEditor.vue';

defineProps<{
    layout: { data: LayoutData };
    templates: { data: TemplateData[] };
    nestedTemplates: { data: TemplateData[] };
    menuOptions: Array<{ value: number; label: string }>;
    crmOptions: CrmOptions | null;
}>();
</script>

<template>
    <LayoutEditor
        :templates="templates.data"
        :nested-templates="nestedTemplates.data"
        :menus="menuOptions"
        :crm-options="crmOptions"
        :layout="layout.data"
    />
</template>
