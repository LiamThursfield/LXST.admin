<script setup lang="ts">
/**
 * A yes/no list filter, e.g. "Hidden from requests": a pill that's filled while the filter is on.
 * The same height as the toolbar's selects, so a row of filters lines up.
 */
defineProps<{
    label: string;
    icon?: string;
    disabled?: boolean;
}>();

const on = defineModel<boolean>({ required: true });
</script>

<template>
    <UButton
        color="neutral"
        :variant="on ? 'solid' : 'outline'"
        :icon="icon"
        :label="label"
        :disabled="disabled"
        :aria-pressed="on"
        class="rounded-full"
        @click="on = !on"
    />
</template>
