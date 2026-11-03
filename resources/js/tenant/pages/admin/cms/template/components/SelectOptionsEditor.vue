<script setup lang="ts">
import { VueDraggable } from 'vue-draggable-plus';
import type { TemplateFieldData, TemplateFieldOption } from '@/types';

/**
 * A select field's options, which can be reordered, and its default. A new option's value is filled
 * in from its label until it's edited. A saved option's value isn't, as content is stored by value.
 */
const props = withDefaults(
    defineProps<{
        /** Where its server errors are, e.g. `fields.0` or `sections.1.fields.2`. */
        errorPrefix: string;
        errors?: Record<string, string | undefined>;
        readonly?: boolean;
    }>(),
    {
        errors: () => ({}),
        readonly: false,
    },
);

const field = defineModel<TemplateFieldData>({ required: true });

const { trans } = useTrans();

const options = computed({
    get: () => field.value.settings.options ?? [],
    set: (list: TemplateFieldOption[]) => {
        field.value.settings.options = list;
    },
});

/** Keys for the list, kept apart from the options so they aren't sent. */
const keys = new WeakMap<TemplateFieldOption, number>();
let nextKey = 0;

function keyFor(option: TemplateFieldOption): number {
    if (!keys.has(option)) {
        keys.set(option, nextKey++);
    }

    return keys.get(option)!;
}

/** The options whose value still follows their label. */
const followingLabel = new WeakSet<TemplateFieldOption>();

function valueFrom(label: string): string {
    return label
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function add() {
    options.value = [...options.value, { label: '', value: '' }];
    followingLabel.add(options.value[options.value.length - 1]);
}

/** Keeps the default on the same option when its value changes. */
function setValue(option: TemplateFieldOption, value: string) {
    if (field.value.settings.default === option.value) {
        field.value.settings.default = value || null;
    }

    option.value = value;
}

function onLabelInput(option: TemplateFieldOption, label: string) {
    option.label = label;

    if (followingLabel.has(option)) {
        setValue(option, valueFrom(label));
    }
}

function onValueInput(option: TemplateFieldOption, value: string) {
    followingLabel.delete(option);
    setValue(option, value);
}

function remove(option: TemplateFieldOption) {
    if (field.value.settings.default === option.value) {
        field.value.settings.default = null;
    }

    options.value = options.value.filter((item) => item !== option);
}

/** Stands in for no default. It can't clash with an option, whose values are alpha-dash. */
const noDefault = ':none';

const defaultItems = computed(() => [
    { label: trans('cms.templates.builder.default_none'), value: noDefault },
    ...options.value
        .filter((option) => option.value)
        .map((option) => ({
            label: option.label || option.value,
            value: option.value,
        })),
]);

const defaultValue = computed({
    get: () => field.value.settings.default ?? noDefault,
    set: (value: string) => {
        field.value.settings.default = value === noDefault ? null : value;
    },
});

function error(key: string): string | undefined {
    return props.errors[`${props.errorPrefix}.settings.${key}`];
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <UFormField
            :label="trans('cms.templates.builder.options')"
            :help="
                readonly
                    ? undefined
                    : trans('cms.templates.builder.options_help')
            "
            :error="error('options')"
            required
        >
            <div class="flex flex-col gap-3">
                <p v-if="!options.length" class="text-muted text-sm">
                    {{ trans('cms.templates.builder.options_empty') }}
                </p>

                <VueDraggable
                    v-else
                    v-model="options"
                    :disabled="readonly"
                    handle=".option-drag-handle"
                    :animation="150"
                    class="flex flex-col gap-2"
                >
                    <div
                        v-for="(option, index) in options"
                        :key="keyFor(option)"
                        class="flex items-start gap-2"
                    >
                        <UButton
                            v-if="!readonly"
                            class="option-drag-handle cursor-grab"
                            color="neutral"
                            variant="ghost"
                            icon="i-lucide-grip-vertical"
                            :aria-label="
                                trans('cms.templates.builder.option_drag')
                            "
                        />
                        <UFormField
                            class="min-w-0 flex-1"
                            :error="error(`options.${index}.label`)"
                        >
                            <UInput
                                :model-value="option.label"
                                :disabled="readonly"
                                :placeholder="
                                    trans('cms.templates.builder.option_label')
                                "
                                :aria-label="
                                    trans('cms.templates.builder.option_label')
                                "
                                class="w-full"
                                @update:model-value="
                                    onLabelInput(option, String($event))
                                "
                            />
                        </UFormField>
                        <UFormField
                            class="min-w-0 flex-1"
                            :error="error(`options.${index}.value`)"
                        >
                            <UInput
                                :model-value="option.value"
                                :disabled="readonly"
                                :placeholder="
                                    trans('cms.templates.builder.option_value')
                                "
                                :aria-label="
                                    trans('cms.templates.builder.option_value')
                                "
                                class="w-full font-mono"
                                @update:model-value="
                                    onValueInput(option, String($event))
                                "
                            />
                        </UFormField>
                        <UButton
                            v-if="!readonly"
                            color="neutral"
                            variant="ghost"
                            icon="i-lucide-x"
                            :aria-label="
                                trans('cms.templates.builder.option_remove')
                            "
                            @click="remove(option)"
                        />
                    </div>
                </VueDraggable>

                <div v-if="!readonly">
                    <UButton
                        color="neutral"
                        variant="subtle"
                        icon="i-lucide-plus"
                        :label="trans('cms.templates.builder.option_add')"
                        @click="add"
                    />
                </div>
            </div>
        </UFormField>

        <UFormField
            :label="trans('cms.templates.builder.default')"
            :help="
                readonly
                    ? undefined
                    : trans('cms.templates.builder.default_help')
            "
            :error="error('default')"
        >
            <USelect
                v-model="defaultValue"
                :items="defaultItems"
                :disabled="readonly"
                class="w-full sm:w-72"
            />
        </UFormField>
    </div>
</template>
