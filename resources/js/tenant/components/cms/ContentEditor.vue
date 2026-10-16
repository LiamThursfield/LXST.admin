<script setup lang="ts">
import type { Component } from 'vue';
import type { ContentValues, TemplateFieldData } from '@/types';
import ComponentContentField from './fields/ComponentContentField.vue';
import ImageContentField from './fields/ImageContentField.vue';
import NumberContentField from './fields/NumberContentField.vue';
import RepeaterContentField from './fields/RepeaterContentField.vue';
import RichTextContentField from './fields/RichTextContentField.vue';
import TextareaContentField from './fields/TextareaContentField.vue';
import TextContentField from './fields/TextContentField.vue';

/**
 * Edits content for a template's fields (layouts, and pages in #51). Values are keyed by field
 * slug, and server errors are expected under the prefix, e.g. `content.{slug}`. Components and
 * repeaters nest another ContentEditor with a longer prefix, e.g. `content.slides.0`.
 */
const props = withDefaults(
    defineProps<{
        fields: TemplateFieldData[];
        prefix?: string;
        errors?: Record<string, string | undefined>;
        readonly?: boolean;
    }>(),
    {
        prefix: 'content',
        errors: () => ({}),
        readonly: false,
    },
);

const values = defineModel<ContentValues>({ required: true });

const { trans } = useTrans();

/** The editor for each field type. Others arrive with their issues (#52, #53). */
const editors: Record<string, Component> = {
    text: TextContentField,
    textarea: TextareaContentField,
    number: NumberContentField,
    wysiwyg: RichTextContentField,
    image: ImageContentField,
    component: ComponentContentField,
    repeater: RepeaterContentField,
};

/** Components and repeaters show their own fields' errors, so they get the errors and their prefix. */
const nestedTypes = ['component', 'repeater'];

function nestedProps(field: TemplateFieldData) {
    return nestedTypes.includes(field.type)
        ? { prefix: `${props.prefix}.${field.slug}`, errors: props.errors }
        : {};
}

/**
 * The first error for a field, including nested ones like an image's `content.hero.file_id`.
 * Components and repeaters only show their own (e.g. too few rows); their fields show the rest.
 */
function errorFor(field: TemplateFieldData): string | undefined {
    const key = `${props.prefix}.${field.slug}`;

    if (props.errors[key] || nestedTypes.includes(field.type)) {
        return props.errors[key];
    }

    return Object.entries(props.errors).find(([errorKey]) =>
        errorKey.startsWith(`${key}.`),
    )?.[1];
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
                v-bind="nestedProps(field)"
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
