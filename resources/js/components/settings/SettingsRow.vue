<script setup lang="ts">
/**
 * One setting on a settings page: its label and description on the left and its fields on the
 * right, stacking on narrow screens.
 */
defineProps<{
    label: string;
    description?: string;
    required?: boolean;
}>();

defineSlots<{
    default(): unknown;
}>();
</script>

<template>
    <div
        class="border-default flex flex-col gap-4 border-t py-6 md:flex-row md:gap-8"
    >
        <div class="md:w-60 md:shrink-0 lg:w-72">
            <h3 class="text-highlighted text-sm font-semibold">
                {{ label }}
                <span v-if="required" class="text-error" aria-hidden="true"
                    >*</span
                >
            </h3>
            <p v-if="description" class="text-muted mt-1 text-sm">
                {{ description }}
            </p>
        </div>
        <div class="flex min-w-0 flex-1 flex-col gap-4">
            <slot />
        </div>
    </div>
</template>
