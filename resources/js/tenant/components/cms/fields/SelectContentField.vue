<script setup lang="ts">
import type { TemplateFieldData } from '@/types';

/**
 * One of the field's options, stored as its value. The default is only shown: nothing is stored until
 * a different option is chosen, so an untouched optional component stays empty and changing the
 * default updates existing content.
 */
const props = defineProps<{
    field: TemplateFieldData;
    disabled?: boolean;
}>();

const value = defineModel<string | null>({ default: null });

const { trans } = useTrans();

/** Stands in for choosing nothing. It can't clash with an option, whose values are alpha-dash. */
const none = ':none';

const options = computed(() => props.field.settings.options ?? []);

const defaultValue = computed(() => props.field.settings.default ?? null);

const isRemoved = computed(
    () =>
        !!value.value &&
        !options.value.some((option) => option.value === value.value),
);

const items = computed(() => [
    ...(defaultValue.value === null && !props.field.is_required
        ? [{ label: trans('cms.content.select.none'), value: none }]
        : []),
    ...options.value,
]);

const selected = computed({
    get: () => {
        if (isRemoved.value) {
            return undefined;
        }

        return value.value ?? defaultValue.value ?? undefined;
    },
    set: (chosen: string | undefined) => {
        value.value =
            !chosen || chosen === none || chosen === defaultValue.value
                ? null
                : chosen;
    },
});
</script>

<template>
    <div class="flex flex-col gap-2">
        <USelect
            v-model="selected"
            :items="items"
            :disabled="disabled"
            :placeholder="trans('cms.content.select.placeholder')"
            class="w-full sm:w-72"
        />
        <UAlert
            v-if="isRemoved && !disabled"
            color="warning"
            variant="subtle"
            icon="i-lucide-triangle-alert"
            :description="
                trans('cms.content.select.removed', { value: value ?? '' })
            "
        />
    </div>
</template>
