<script setup lang="ts">
import type { MenuPageOption, TemplateData } from '@/types';
import SavedComponentEditor from './components/SavedComponentEditor.vue';

defineProps<{
    templates: { data: TemplateData[] };
    nestedTemplates: { data: TemplateData[] };
    pageOptions: MenuPageOption[];
}>();
</script>

<template>
    <SavedComponentEditor
        :templates="templates.data"
        :nested-templates="nestedTemplates.data"
        :page-options="pageOptions"
    />
</template>
