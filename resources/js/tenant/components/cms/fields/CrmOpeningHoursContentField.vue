<script setup lang="ts">
import { useCrmOptions } from '../nestedTemplates';

/**
 * A location whose opening hours the page shows, stored by id.
 * Without CRM the field is hidden from the site and keeps its value.
 */
defineProps<{
    disabled?: boolean;
}>();

const value = defineModel<number | null>({ default: null });

const { trans } = useTrans();

const crm = useCrmOptions();

/** Drops `type`, which Nuxt UI reads as the item kind (label, separator). */
const locations = computed(() =>
    (crm.value?.organisationUnits ?? [])
        .filter((unit) => unit.type === 'location')
        .map(({ label, value }) => ({ label, value })),
);
</script>

<template>
    <p v-if="!crm" class="text-muted text-sm">
        {{ trans('cms.content.crm.off') }}
    </p>
    <USelectMenu
        v-else-if="locations.length"
        v-model.nullable="value"
        :items="locations"
        value-key="value"
        :disabled="disabled"
        :placeholder="trans('cms.content.crm.location_placeholder')"
        class="w-full sm:w-72"
    />
    <p v-else class="text-muted text-sm">
        {{ trans('cms.content.crm.no_locations') }}
    </p>
</template>
