<script setup lang="ts">
import type { CrmOptions } from '@/tenant/components/cms/nestedTemplates';
import type { PageData, TemplateData } from '@/types';
import PageEditor from './components/PageEditor.vue';

defineProps<{
    page: { data: PageData };
    templates: { data: TemplateData[] };
    nestedTemplates: { data: TemplateData[] };
    menuOptions: Array<{ value: number; label: string }>;
    crmOptions: CrmOptions | null;
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
        :layouts="layouts"
        :parents="parents"
        :page="page.data"
        readonly
    />
</template>
