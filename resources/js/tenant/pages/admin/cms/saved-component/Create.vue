<script setup lang="ts">
import type { TemplateData } from '@/types';
import SavedComponentEditor from './components/SavedComponentEditor.vue';

defineProps<{
    templates: { data: TemplateData[] };
    nestedTemplates: { data: TemplateData[] };
}>();
</script>

<template>
    <SavedComponentEditor
        :templates="templates.data"
        :nested-templates="nestedTemplates.data"
    />
</template>
