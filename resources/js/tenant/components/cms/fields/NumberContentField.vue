<script setup lang="ts">
import type { TemplateFieldData } from '@/types';

defineProps<{
    field: TemplateFieldData;
    disabled?: boolean;
}>();

const value = defineModel<number | null>({ default: null });
</script>

<template>
    <UInputNumber
        v-model="value"
        :disabled="disabled"
        :min="field.settings.min ?? undefined"
        :max="field.settings.max ?? undefined"
        :format-options="{ useGrouping: false, maximumFractionDigits: 10 }"
        class="w-full sm:w-64"
    />
</template>
