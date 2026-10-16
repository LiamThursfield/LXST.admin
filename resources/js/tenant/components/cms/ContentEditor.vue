<script setup lang="ts">
import type { Component } from 'vue';
import type { ContentValues, TemplateFieldData } from '@/types';
import ImageContentField from './fields/ImageContentField.vue';
import NumberContentField from './fields/NumberContentField.vue';
import RichTextContentField from './fields/RichTextContentField.vue';
import TextareaContentField from './fields/TextareaContentField.vue';
import TextContentField from './fields/TextContentField.vue';

/**
 * Edits content for a template's fields (layouts, and pages in #51). Values are keyed by field
 * slug, and server errors are expected as `content.{slug}`.
 */
const props = withDefaults(
    defineProps<{
        fields: TemplateFieldData[];
        errors?: Record<string, string | undefined>;
        readonly?: boolean;
    }>(),
    {
        errors: () => ({}),
        readonly: false,
    },
);

const values = defineModel<ContentValues>({ required: true });

const { trans } = useTrans();

/** The editor for each field type. Others arrive with their issues (#50–#53). */
const editors: Record<string, Component> = {
    text: TextContentField,
    textarea: TextareaContentField,
    number: NumberContentField,
    wysiwyg: RichTextContentField,
    image: ImageContentField,
};

/** The first error for a field, including nested ones like an image's `content.hero.file_id`. */
function errorFor(field: TemplateFieldData): string | undefined {
    const key = `content.${field.slug}`;

    return (
        props.errors[key] ??
        Object.entries(props.errors).find(([errorKey]) =>
            errorKey.startsWith(`${key}.`),
        )?.[1]
    );
}

function editorFor(field: TemplateFieldData): Component | undefined {
    return editors[field.type];
}

const hasFields = computed(() => props.fields.length > 0);
</script>

<template>
    <div class="flex flex-col gap-4">
        <p v-if="!hasFields" class="text-muted text-sm">
            {{ trans('cms.content.empty') }}
        </p>

        <UFormField
            v-for="field in fields"
            :key="field.slug"
            :label="field.name"
            :help="field.description ?? undefined"
            :error="errorFor(field)"
            :required="field.is_required"
        >
            <component
                :is="editorFor(field)"
                v-if="editorFor(field)"
                v-model="values[field.slug]"
                :field="field"
                :disabled="readonly"
            />
            <p v-else class="text-muted text-sm">
                {{
                    trans('cms.content.unsupported', {
                        type: field.type_label ?? field.type,
                    })
                }}
            </p>
        </UFormField>
    </div>
</template>
