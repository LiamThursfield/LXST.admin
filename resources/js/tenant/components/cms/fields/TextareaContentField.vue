<script setup lang="ts">
import type { TemplateFieldData } from '@/types';

defineProps<{
    field: TemplateFieldData;
    disabled?: boolean;
}>();

const value = defineModel<string | null>({ default: null });
</script>

<template>
    <UTextarea
        v-model="value"
        :disabled="disabled"
        :rows="field.settings.rows ?? 4"
        :maxlength="field.settings.max_length ?? undefined"
        autoresize
        class="w-full"
    />
</template>
