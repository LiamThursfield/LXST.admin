<script setup lang="ts">
import { breakpointsTailwind, useBreakpoints } from '@vueuse/core';

/**
 * The chosen field's (or section's) settings in a field builder. On wide screens it's a card
 * beside the field list that stays in view while scrolling; on narrow ones it opens as a
 * slideover. With nothing chosen, the card says how to choose something.
 */
withDefaults(
    defineProps<{
        title?: string | null;
        description?: string | null;
        /** Shown in the card when nothing is chosen. */
        emptyText: string;
    }>(),
    {
        title: null,
        description: null,
    },
);

/** Whether something is chosen; closing the slideover clears the choice. */
const open = defineModel<boolean>('open', { required: true });

defineSlots<{
    /** Buttons in the header, e.g. Duplicate and Remove. */
    actions?(): unknown;
    default(): unknown;
}>();

const isWide = useBreakpoints(breakpointsTailwind).greaterOrEqual('lg');
</script>

<template>
    <aside
        v-if="isWide"
        class="bg-elevated/50 ring-default flex flex-col rounded-lg ring lg:sticky lg:top-0 lg:max-h-[calc(100dvh-10rem)]"
        :aria-label="title ?? undefined"
    >
        <template v-if="open">
            <div
                class="border-default flex items-center gap-2 border-b px-4 py-3.5"
            >
                <div class="min-w-0 flex-1">
                    <h2 class="text-highlighted truncate font-semibold">
                        {{ title }}
                    </h2>
                    <p v-if="description" class="text-muted text-sm">
                        {{ description }}
                    </p>
                </div>
                <slot name="actions" />
            </div>
            <div class="overflow-y-auto p-4 sm:p-6">
                <slot />
            </div>
        </template>
        <p v-else class="text-muted p-6 text-center text-sm">
            {{ emptyText }}
        </p>
    </aside>

    <USlideover
        v-else
        v-model:open="open"
        :title="title ?? undefined"
        :description="description ?? undefined"
    >
        <template #actions>
            <slot name="actions" />
        </template>
        <template #body>
            <slot />
        </template>
    </USlideover>
</template>
