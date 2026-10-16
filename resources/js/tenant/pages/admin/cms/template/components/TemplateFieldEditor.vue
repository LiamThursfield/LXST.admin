<script setup lang="ts">
import type {
    NestedTemplateOption,
    SelectItem,
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
        /** The templates component and repeater fields can use. */
        nestedTemplates?: NestedTemplateOption[];
        /** For limiting an organisation unit field to one type of unit. */
        organisationUnitTypes?: SelectItem[];
        errors?: Record<string, string | undefined>;
        readonly?: boolean;
    }>(),
    {
        nestedTemplates: () => [],
        organisationUnitTypes: () => [],
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
    props.fieldTypes
        // Types needing a module the tenant doesn't have aren't offered, unless the field already is one
        .filter((type) => !type.needsModule || type.value === field.value.type)
        .map((type) => ({
            value: type.value,
            label: type.isAvailable
                ? type.label
                : `${type.label} (${trans('cms.templates.builder.coming_soon')})`,
            disabled: !type.isAvailable && type.value !== field.value.type,
        })),
);

const typeSettingKeys = computed<TemplateFieldSettingKey[]>(
    () =>
        props.fieldTypes.find((type) => type.value === field.value.type)
            ?.settingKeys ?? [],
);

/** The number settings, e.g. max length or a repeater's fewest and most rows. */
const settingKeys = computed(() =>
    typeSettingKeys.value.filter(
        (key) => key !== 'template_id' && key !== 'organisation_unit_type',
    ),
);

/** Component and repeater fields choose a template of the same type. */
const nestedTemplateItems = computed(() =>
    props.nestedTemplates
        .filter((template) => template.type === field.value.type)
        .map((template) => ({ label: template.name, value: template.id })),
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
                    v-if="typeSettingKeys.includes('template_id')"
                    :label="trans('cms.templates.builder.template_id')"
                    :help="
                        nestedTemplateItems.length || readonly
                            ? undefined
                            : trans(
                                  'cms.templates.builder.no_nested_templates',
                                  {
                                      type:
                                          fieldTypes.find(
                                              (type) =>
                                                  type.value === field.type,
                                          )?.label ?? field.type,
                                  },
                              )
                    "
                    :error="error('settings.template_id')"
                    required
                >
                    <USelect
                        v-model="field.settings.template_id"
                        :items="nestedTemplateItems"
                        :disabled="readonly"
                        :placeholder="
                            trans(
                                'cms.templates.builder.template_id_placeholder',
                            )
                        "
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    v-if="typeSettingKeys.includes('organisation_unit_type')"
                    :label="
                        trans('cms.templates.builder.organisation_unit_type')
                    "
                    :error="error('settings.organisation_unit_type')"
                >
                    <USelect
                        v-model="field.settings.organisation_unit_type"
                        :items="organisationUnitTypes"
                        :disabled="readonly"
                        :placeholder="
                            trans(
                                'cms.templates.builder.organisation_unit_type_placeholder',
                            )
                        "
                        class="w-full"
                    />
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
