<script setup lang="ts">
import type { TemplateFieldData } from '@/types';
import { useMenuOptions } from '../nestedTemplates';

/** One of the site's menus, stored by id. */
defineProps<{
    field: TemplateFieldData;
    disabled?: boolean;
}>();

const value = defineModel<number | null>({ default: null });

const { trans } = useTrans();

const menus = useMenuOptions();
</script>

<template>
    <USelect
        v-if="menus.length"
        v-model="value"
        :items="menus"
        :disabled="disabled"
        :placeholder="trans('cms.content.menu.placeholder')"
        class="w-full sm:w-72"
    />
    <p v-else class="text-muted text-sm">
        {{ trans('cms.content.menu.none') }}
    </p>
</template>
