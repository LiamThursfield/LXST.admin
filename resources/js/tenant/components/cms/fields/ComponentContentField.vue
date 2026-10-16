<script setup lang="ts">
import type { ContentValues, TemplateFieldData } from '@/types';
import ContentEditor from '../ContentEditor.vue';
import { useNestedTemplate } from '../nestedTemplates';

/** One set of a component template's fields, stored as an object keyed by their slugs. */
const props = defineProps<{
    field: TemplateFieldData;
    /** Where this field's errors are, e.g. `content.call_to_action`. */
    prefix: string;
    errors?: Record<string, string | undefined>;
    disabled?: boolean;
}>();

const value = defineModel<ContentValues | null>({ default: null });

// The nested editor fills in the object's keys, so it needs one to start from
if (value.value === null || typeof value.value !== 'object') {
    value.value = {};
}

const template = useNestedTemplate(() => props.field.settings.template_id);
</script>

<template>
    <div class="border-default rounded-md border p-4">
        <ContentEditor
            v-if="template && value"
            v-model="value"
            :fields="template.fields ?? []"
            :prefix="prefix"
            :errors="errors"
            :readonly="disabled"
        />
    </div>
</template>
