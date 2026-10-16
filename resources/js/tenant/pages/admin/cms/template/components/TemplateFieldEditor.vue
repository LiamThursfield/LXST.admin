<script setup lang="ts">
import type {
    TemplateFieldData,
    TemplateFieldSettingKey,
    TemplateFieldTypeOption,
} from '@/types';

const props = withDefaults(
    defineProps<{
        /** The field's position in the list, used for server error keys like `fields.0.name`. */
        index: number;
        /** The field types this type of template allows. */
        fieldTypes: TemplateFieldTypeOption[];
        errors?: Record<string, string | undefined>;
        readonly?: boolean;
    }>(),
    {
        errors: () => ({}),
        readonly: false,
    },
);

defineEmits<{
    remove: [];
}>();

const field = defineModel<TemplateFieldData>({ required: true });

const { trans } = useTrans();

/**
 * Types without a content editor yet are listed but can't be chosen, except for an existing
 * field that already has one.
 */
const typeItems = computed(() =>
    props.fieldTypes.map((type) => ({
        value: type.value,
        label: type.isAvailable
            ? type.label
            : `${type.label} (${trans('cms.templates.builder.coming_soon')})`,
        disabled: !type.isAvailable && type.value !== field.value.type,
    })),
);

/** Number settings only; component and repeater templates are chosen once those editors exist (#50). */
const settingKeys = computed<TemplateFieldSettingKey[]>(() =>
    (
        props.fieldTypes.find((type) => type.value === field.value.type)
            ?.settingKeys ?? []
    ).filter((key) => key !== 'template_id'),
);

function error(key: string): string | undefined {
    return props.errors[`fields.${props.index}.${key}`];
}
</script>

<template>
    <UCard :ui="{ body: 'p-4 sm:p-4' }">
        <div class="flex gap-3">
            <UButton
                v-if="!readonly"
                class="drag-handle cursor-grab self-start"
                color="neutral"
                variant="ghost"
                icon="i-lucide-grip-vertical"
                :aria-label="trans('cms.templates.builder.drag')"
            />

            <div class="grid flex-1 grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    :label="trans('cms.templates.builder.type')"
                    :error="error('type')"
                    required
                >
                    <USelect
                        v-model="field.type"
                        :disabled="readonly"
                        :items="typeItems"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="trans('cms.templates.builder.name')"
                    :error="error('name')"
                    required
                >
                    <UInput
                        v-model="field.name"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="trans('cms.templates.builder.slug')"
                    :help="
                        readonly
                            ? undefined
                            : trans('cms.templates.builder.slug_help')
                    "
                    :error="error('slug')"
                >
                    <UInput
                        v-model="field.slug"
                        :disabled="readonly"
                        class="w-full font-mono"
                    />
                </UFormField>

                <UFormField
                    :label="trans('cms.templates.builder.description')"
                    :help="
                        readonly
                            ? undefined
                            : trans('cms.templates.builder.description_help')
                    "
                    :error="error('description')"
                >
                    <UInput
                        v-model="field.description"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>

                <UFormField :label="trans('cms.templates.builder.is_required')">
                    <USwitch v-model="field.is_required" :disabled="readonly" />
                </UFormField>

                <UFormField
                    v-for="key in settingKeys"
                    :key="key"
                    :label="trans(`cms.templates.builder.${key}`)"
                    :error="error(`settings.${key}`)"
                >
                    <UInput
                        v-model.number="field.settings[key]"
                        :disabled="readonly"
                        class="w-full"
                        type="number"
                    />
                </UFormField>
            </div>

            <UButton
                v-if="!readonly"
                class="self-start"
                color="neutral"
                variant="ghost"
                icon="i-lucide-trash"
                :aria-label="trans('cms.templates.builder.remove')"
                @click="$emit('remove')"
            />
        </div>
    </UCard>
</template>
