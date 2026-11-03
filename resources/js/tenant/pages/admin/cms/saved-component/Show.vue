<script setup lang="ts">
import type { SavedComponentData, TemplateData } from '@/types';
import SavedComponentEditor from './components/SavedComponentEditor.vue';

defineProps<{
    savedComponent: { data: SavedComponentData };
    templates: { data: TemplateData[] };
    nestedTemplates: { data: TemplateData[] };
}>();
</script>

<template>
    <SavedComponentEditor
        :templates="templates.data"
        :nested-templates="nestedTemplates.data"
        :saved-component="savedComponent.data"
        readonly
    />
</template>
