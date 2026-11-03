<script setup lang="ts">
import type {
    MenuPageOption,
    SavedComponentData,
    SavedComponentUsage,
    TemplateData,
} from '@/types';
import SavedComponentEditor from './components/SavedComponentEditor.vue';

defineProps<{
    savedComponent: { data: SavedComponentData };
    templates: { data: TemplateData[] };
    nestedTemplates: { data: TemplateData[] };
    pageOptions: MenuPageOption[];
    usage: SavedComponentUsage[];
}>();
</script>

<template>
    <SavedComponentEditor
        :templates="templates.data"
        :nested-templates="nestedTemplates.data"
        :page-options="pageOptions"
        :saved-component="savedComponent.data"
        :usage="usage"
        readonly
    />
</template>
