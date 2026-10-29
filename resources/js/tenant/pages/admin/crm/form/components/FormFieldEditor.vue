<script setup lang="ts">
import { computed } from 'vue';
import type {
    FormFieldData,
    FormFieldSettingKey,
    FormFieldTypeOption,
} from '@/types';

/**
 * The chosen field's settings in the form builder's panel: its label, key, type and whether it's
 * required, then the settings its type has.
 */
const props = withDefaults(
    defineProps<{
        /** Where its server errors are, e.g. `fields.0`. */
        errorPrefix: string;
        fieldTypes: FormFieldTypeOption[];
        errors?: Record<string, string | undefined>;
        readonly?: boolean;
    }>(),
    {
        errors: () => ({}),
        readonly: false,
    },
);

const field = defineModel<FormFieldData>({ required: true });

const { trans } = useTrans();

const fieldType = computed(() =>
    props.fieldTypes.find((type) => type.value === field.value.type),
);

const settingKeys = computed<FormFieldSettingKey[]>(
    () => fieldType.value?.settingKeys ?? [],
);

function error(key: string): string | undefined {
    return props.errors[`${props.errorPrefix}.${key}`];
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <UFormField
                :label="trans('crm.forms.builder.name')"
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
                :label="trans('crm.forms.builder.slug')"
                :help="
                    readonly ? undefined : trans('crm.forms.builder.slug_help')
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
                class="sm:col-span-2"
                :label="trans('crm.forms.builder.type')"
                :help="
                    fieldType?.isContactField && !readonly
                        ? trans('crm.forms.builder.contact_field_help')
                        : undefined
                "
                :error="error('type')"
                required
            >
                <USelect
                    v-model="field.type"
                    :disabled="readonly"
                    :items="fieldTypes"
                    class="w-full"
                />
            </UFormField>
        </div>

        <div
            class="border-default flex items-center justify-between gap-3 rounded-md border p-3"
        >
            <div>
                <p class="text-highlighted text-sm font-medium">
                    {{ trans('crm.forms.builder.is_required') }}
                </p>
                <p class="text-muted text-sm">
                    {{ trans('crm.forms.builder.is_required_help') }}
                </p>
            </div>
            <USwitch
                v-model="field.is_required"
                :disabled="readonly"
                :aria-label="trans('crm.forms.builder.is_required')"
            />
        </div>

        <div class="flex flex-col gap-3">
            <h3
                class="text-muted text-xs font-semibold uppercase tracking-wide"
            >
                {{
                    trans('crm.forms.builder.type_settings', {
                        type: fieldType?.label ?? field.type,
                    })
                }}
            </h3>

            <p v-if="!settingKeys.length" class="text-muted text-sm">
                {{ trans('crm.forms.builder.no_type_settings') }}
            </p>

            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <UFormField
                    v-for="key in settingKeys"
                    :key="key"
                    :label="trans(`crm.forms.builder.${key}`)"
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
