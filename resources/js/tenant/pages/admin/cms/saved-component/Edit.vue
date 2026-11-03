<script setup lang="ts">
import type {
    SavedComponentData,
    SavedComponentUsage,
    TemplateData,
} from '@/types';
import SavedComponentEditor from './components/SavedComponentEditor.vue';

defineProps<{
    savedComponent: { data: SavedComponentData };
    templates: { data: TemplateData[] };
    nestedTemplates: { data: TemplateData[] };
    usage: SavedComponentUsage[];
}>();
</script>

<template>
    <SavedComponentEditor
        :templates="templates.data"
        :nested-templates="nestedTemplates.data"
        :saved-component="savedComponent.data"
        :usage="usage"
    />
</template>
