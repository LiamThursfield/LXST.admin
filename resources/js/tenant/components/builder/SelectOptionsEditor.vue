<script setup lang="ts">
import { VueDraggable } from 'vue-draggable-plus';
import type { SelectFieldOption, SelectFieldSettings } from '@/types';

/**
 * A builder select field's options, which can be reordered, and its default (CMS templates, CRM
 * forms). A new option's value is filled in from its label until it's edited. A saved option's
 * value isn't, as what's stored is the value.
 */
const props = withDefaults(
    defineProps<{
        /** Where its server errors are, e.g. `fields.0` or `sections.1.fields.2`. */
        errorPrefix: string;
        /** What the options' labels and values are used for, shown under the list. */
        optionsHelp?: string;
        /** What the default does, shown under it. */
        defaultHelp?: string;
        errors?: Record<string, string | undefined>;
        readonly?: boolean;
    }>(),
    {
        optionsHelp: undefined,
        defaultHelp: undefined,
        errors: () => ({}),
        readonly: false,
    },
);

/** The field's settings, whose `options` and `default` are edited here. */
const settings = defineModel<SelectFieldSettings>('settings', {
    required: true,
});

const { trans } = useTrans();

const options = computed({
    get: () => settings.value.options ?? [],
    set: (list: SelectFieldOption[]) => {
        settings.value.options = list;
    },
});

/** Keys for the list, kept apart from the options so they aren't sent. */
const keys = new WeakMap<SelectFieldOption, number>();
let nextKey = 0;

function keyFor(option: SelectFieldOption): number {
    if (!keys.has(option)) {
        keys.set(option, nextKey++);
    }

    return keys.get(option)!;
}

/** The options whose value still follows their label. */
const followingLabel = new WeakSet<SelectFieldOption>();

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
function setValue(option: SelectFieldOption, value: string) {
    if (settings.value.default === option.value) {
        settings.value.default = value || null;
    }

    option.value = value;
}

function onLabelInput(option: SelectFieldOption, label: string) {
    option.label = label;

    if (followingLabel.has(option)) {
        setValue(option, valueFrom(label));
    }
}

function onValueInput(option: SelectFieldOption, value: string) {
    followingLabel.delete(option);
    setValue(option, value);
}

function remove(option: SelectFieldOption) {
    if (settings.value.default === option.value) {
        settings.value.default = null;
    }

    options.value = options.value.filter((item) => item !== option);
}

/** Stands in for no default. It can't clash with an option, whose values are alpha-dash. */
const noDefault = ':none';

const defaultItems = computed(() => [
    { label: trans('common.builder.options.default_none'), value: noDefault },
    ...options.value
        .filter((option) => option.value)
        .map((option) => ({
            label: option.label || option.value,
            value: option.value,
        })),
]);

const defaultValue = computed({
    get: () => settings.value.default ?? noDefault,
    set: (value: string) => {
        settings.value.default = value === noDefault ? null : value;
    },
});

function error(key: string): string | undefined {
    return props.errors[`${props.errorPrefix}.settings.${key}`];
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <UFormField
            :label="trans('common.builder.options.title')"
            :help="readonly ? undefined : optionsHelp"
            :error="error('options')"
            required
        >
            <div class="flex flex-col gap-3">
                <p v-if="!options.length" class="text-muted text-sm">
                    {{ trans('common.builder.options.empty') }}
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
                            :aria-label="trans('common.builder.drag')"
                        />
                        <UFormField
                            class="min-w-0 flex-1"
                            :error="error(`options.${index}.label`)"
                        >
                            <UInput
                                :model-value="option.label"
                                :disabled="readonly"
                                :placeholder="
                                    trans('common.builder.options.label')
                                "
                                :aria-label="
                                    trans('common.builder.options.label')
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
                                    trans('common.builder.options.value')
                                "
                                :aria-label="
                                    trans('common.builder.options.value')
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
                            :aria-label="trans('common.builder.options.remove')"
                            @click="remove(option)"
                        />
                    </div>
                </VueDraggable>

                <div v-if="!readonly">
                    <UButton
                        color="neutral"
                        variant="subtle"
                        icon="i-lucide-plus"
                        :label="trans('common.builder.options.add')"
                        @click="add"
                    />
                </div>
            </div>
        </UFormField>

        <UFormField
            :label="trans('common.builder.options.default')"
            :help="readonly ? undefined : defaultHelp"
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
