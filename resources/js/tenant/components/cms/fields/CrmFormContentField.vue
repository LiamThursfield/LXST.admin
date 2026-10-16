<script setup lang="ts">
import type { TemplateFieldData } from '@/types';
import { useCrmOptions } from '../nestedTemplates';

/** A CRM form, stored by id. Without CRM the field is hidden from the site and keeps its value. */
defineProps<{
    field: TemplateFieldData;
    disabled?: boolean;
}>();

const value = defineModel<number | null>({ default: null });

const { trans } = useTrans();

const crm = useCrmOptions();
</script>

<template>
    <p v-if="!crm" class="text-muted text-sm">
        {{ trans('cms.content.crm.off') }}
    </p>
    <USelect
        v-else-if="crm.forms.length"
        v-model.nullable="value"
        :items="crm.forms"
        :disabled="disabled"
        :placeholder="trans('cms.content.crm.form_placeholder')"
        class="w-full sm:w-72"
    />
    <p v-else class="text-muted text-sm">
        {{ trans('cms.content.crm.no_forms') }}
    </p>
</template>
