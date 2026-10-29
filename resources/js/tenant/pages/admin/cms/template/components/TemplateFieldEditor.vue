<script setup lang="ts">
import type {
    NestedTemplateOption,
    SelectItem,
    TemplateFieldData,
    TemplateFieldSettingKey,
    TemplateFieldTypeOption,
    TemplateFieldWidth,
} from '@/types';

/**
 * The chosen field's settings in the template builder's panel: its name, slug, type, width, help
 * text and whether it's required, then the settings its type has.
 */
const props = withDefaults(
    defineProps<{
        /** Where its server errors are, e.g. `fields.0` or `sections.1.fields.2`. */
        errorPrefix: string;
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

const typeLabel = computed(
    () =>
        props.fieldTypes.find((type) => type.value === field.value.type)
            ?.label ?? field.value.type,
);

const hasTypeSettings = computed(() => typeSettingKeys.value.length > 0);

/** Component and repeater fields choose a template of the same type. */
const nestedTemplateItems = computed(() =>
    props.nestedTemplates
        .filter((template) => template.type === field.value.type)
        .map((template) => ({ label: template.name, value: template.id })),
);

function error(key: string): string | undefined {
    return props.errors[`${props.errorPrefix}.${key}`];
}

const widthItems = computed(() => [
    { label: trans('cms.templates.builder.width_full'), value: 'full' },
    { label: trans('cms.templates.builder.width_half'), value: 'half' },
]);

/** Full width is the default, so it's saved as no width at all. */
const width = computed({
    get: (): TemplateFieldWidth => field.value.settings.width ?? 'full',
    set: (value: TemplateFieldWidth) => {
        field.value.settings.width = value === 'half' ? 'half' : null;
    },
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                :label="trans('cms.templates.builder.width')"
                :help="
                    readonly
                        ? undefined
                        : trans('cms.templates.builder.width_help')
                "
                :error="error('settings.width')"
            >
                <URadioGroup
                    v-model="width"
                    :items="widthItems"
                    :disabled="readonly"
                    orientation="horizontal"
                    variant="table"
                />
            </UFormField>

            <UFormField
                class="sm:col-span-2"
                :label="trans('cms.templates.builder.description')"
                :help="
                    readonly
                        ? undefined
                        : trans('cms.templates.builder.description_help')
                "
                :error="error('description')"
            >
                <UInput
                    v-model.nullable="field.description"
                    :disabled="readonly"
                    class="w-full"
                />
            </UFormField>
        </div>

        <div
            class="border-default flex items-center justify-between gap-3 rounded-md border p-3"
        >
            <div>
                <p class="text-highlighted text-sm font-medium">
                    {{ trans('cms.templates.builder.is_required') }}
                </p>
                <p class="text-muted text-sm">
                    {{ trans('cms.templates.builder.is_required_help') }}
                </p>
            </div>
            <USwitch
                v-model="field.is_required"
                :disabled="readonly"
                :aria-label="trans('cms.templates.builder.is_required')"
            />
        </div>

        <div class="flex flex-col gap-3">
            <h3
                class="text-muted text-xs font-semibold uppercase tracking-wide"
            >
                {{
                    trans('cms.templates.builder.type_settings', {
                        type: typeLabel,
                    })
                }}
            </h3>

            <p v-if="!hasTypeSettings" class="text-muted text-sm">
                {{ trans('cms.templates.builder.no_type_settings') }}
            </p>

            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <UFormField
                    v-if="typeSettingKeys.includes('template_id')"
                    :label="trans('cms.templates.builder.template_id')"
                    :help="
                        nestedTemplateItems.length || readonly
                            ? undefined
                            : trans(
                                  'cms.templates.builder.no_nested_templates',
                                  {
                                      type: typeLabel,
                                  },
                              )
                    "
                    :error="error('settings.template_id')"
                    required
                >
                    <USelect
                        v-model.nullable="field.settings.template_id"
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
                        v-model.nullable="field.settings.organisation_unit_type"
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
                        v-model.number.nullable="field.settings[key]"
                        :disabled="readonly"
                        class="w-full"
                        type="number"
                    />
                </UFormField>
            </div>
        </div>
    </div>
</template>
