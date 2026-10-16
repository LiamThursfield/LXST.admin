<script setup lang="ts">
import type { TemplateFieldData } from '@/types';
import { useCrmOptions } from '../nestedTemplates';

/**
 * A CRM organisation unit, stored by id, limited to one type if the field's settings say so.
 * Without CRM the field is hidden from the site and keeps its value.
 */
const props = defineProps<{
    field: TemplateFieldData;
    disabled?: boolean;
}>();

const value = defineModel<number | null>({ default: null });

const { trans } = useTrans();

const crm = useCrmOptions();

/** Drops `type`, which Nuxt UI reads as the item kind (label, separator). */
const units = computed(() => {
    const type = props.field.settings.organisation_unit_type;

    return (crm.value?.organisationUnits ?? [])
        .filter((unit) => !type || unit.type === type)
        .map(({ label, value }) => ({ label, value }));
});
</script>

<template>
    <p v-if="!crm" class="text-muted text-sm">
        {{ trans('cms.content.crm.off') }}
    </p>
    <USelectMenu
        v-else-if="units.length"
        v-model.nullable="value"
        :items="units"
        value-key="value"
        :disabled="disabled"
        :placeholder="trans('cms.content.crm.organisation_unit_placeholder')"
        class="w-full sm:w-72"
    />
    <p v-else class="text-muted text-sm">
        {{ trans('cms.content.crm.no_organisation_units') }}
    </p>
</template>
