<script setup lang="ts">
import type { TemplateFieldData } from '@/types';

defineProps<{
    field: TemplateFieldData;
    disabled?: boolean;
}>();

const value = defineModel<string | null>({ default: null });
</script>

<template>
    <UInput
        v-model="value"
        :disabled="disabled"
        :maxlength="field.settings.max_length ?? 255"
        class="w-full"
    />
</template>
