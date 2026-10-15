<script setup lang="ts">
import { computed } from 'vue';
import type {
    FormFieldData,
    FormFieldSettingKey,
    FormFieldTypeOption,
} from '@/types';

const props = withDefaults(
    defineProps<{
        /** The field's position in the list, used for server error keys like `fields.0.name`. */
        index: number;
        fieldTypes: FormFieldTypeOption[];
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

const field = defineModel<FormFieldData>({ required: true });

const { trans } = useTrans();

const fieldType = computed(() =>
    props.fieldTypes.find((type) => type.value === field.value.type),
);

const settingKeys = computed<FormFieldSettingKey[]>(
    () => fieldType.value?.settingKeys ?? [],
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
                :aria-label="trans('crm.forms.builder.drag')"
            />

            <div class="grid flex-1 grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    :label="trans('crm.forms.builder.type')"
                    :name="`fields.${index}.type`"
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

                <UFormField
                    :label="trans('crm.forms.builder.name')"
                    :name="`fields.${index}.name`"
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
                        readonly
                            ? undefined
                            : trans('crm.forms.builder.slug_help')
                    "
                    :name="`fields.${index}.slug`"
                    :error="error('slug')"
                >
                    <UInput
                        v-model="field.slug"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="trans('crm.forms.builder.is_required')"
                    :name="`fields.${index}.is_required`"
                >
                    <USwitch v-model="field.is_required" :disabled="readonly" />
                </UFormField>

                <UFormField
                    v-for="key in settingKeys"
                    :key="key"
                    :label="trans(`crm.forms.builder.${key}`)"
                    :name="`fields.${index}.settings.${key}`"
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
                :aria-label="trans('crm.forms.builder.remove')"
                @click="$emit('remove')"
            />
        </div>
    </UCard>
</template>
