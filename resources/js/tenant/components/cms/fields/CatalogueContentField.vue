<script setup lang="ts">
import type { TemplateFieldData } from '@/types';
import { useCatalogueOptions } from '../nestedTemplates';

/**
 * A catalogue (a menu, price list or products), stored by id. Drafts can be chosen but aren't shown
 * on the site until they're published. Without the module the field is hidden from the site and
 * keeps its value.
 */
defineProps<{
    field: TemplateFieldData;
    disabled?: boolean;
}>();

const value = defineModel<number | null>({ default: null });

const { trans } = useTrans();

const catalogues = useCatalogueOptions();

const items = computed(() =>
    (catalogues.value ?? []).map((catalogue) => ({
        value: catalogue.value,
        label: catalogue.published
            ? catalogue.label
            : `${catalogue.label} (${trans('cms.content.catalogue.draft')})`,
    })),
);

const isDraft = computed(
    () =>
        value.value !== null &&
        catalogues.value?.find((catalogue) => catalogue.value === value.value)
            ?.published === false,
);
</script>

<template>
    <p v-if="!catalogues" class="text-muted text-sm">
        {{ trans('cms.content.catalogue.off') }}
    </p>
    <div v-else-if="items.length" class="flex flex-col gap-1">
        <USelect
            v-model.nullable="value"
            :items="items"
            :disabled="disabled"
            :placeholder="trans('cms.content.catalogue.placeholder')"
            class="w-full sm:w-72"
        />
        <p v-if="isDraft" class="text-warning text-sm">
            {{ trans('cms.content.catalogue.draft_help') }}
        </p>
    </div>
    <p v-else class="text-muted text-sm">
        {{ trans('cms.content.catalogue.none') }}
    </p>
</template>
