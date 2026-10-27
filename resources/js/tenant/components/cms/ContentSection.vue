<script setup lang="ts">
import { useCollapsible } from './contentState';

/**
 * A card of content fields that can be collapsed. Open, it shows the description; collapsed, a
 * one-line summary of what's in it. Collapsed fields stay mounted, just hidden, so they keep
 * their state and opening one never counts as a change.
 */
const props = withDefaults(
    defineProps<{
        title: string;
        /** Remembers whether it's open, e.g. `content.features`. */
        stateKey: string;
        description?: string | null;
        summary?: string | null;
        /** The first error inside, shown while collapsed. */
        error?: string | null;
        required?: boolean;
    }>(),
    {
        description: null,
        summary: null,
        error: null,
        required: false,
    },
);

const { open, toggle } = useCollapsible(() => props.stateKey, {
    openByDefault: true,
    hasError: () => !!props.error,
});

const bodyId = useId();

const subtitle = computed(() => {
    if (open.value) {
        return props.description;
    }

    return props.error ?? props.summary;
});
</script>

<template>
    <section
        class="bg-elevated/50 rounded-lg ring"
        :class="error ? 'ring-error' : 'ring-default'"
        v-bind="error ? { 'data-content-error': '' } : {}"
    >
        <button
            type="button"
            class="focus-visible:outline-primary flex w-full items-center gap-2.5 rounded-lg px-4 py-3.5 text-left focus-visible:outline-2"
            :aria-expanded="open"
            :aria-controls="bodyId"
            @click="toggle"
        >
            <UIcon
                name="i-lucide-chevron-down"
                class="text-muted size-4 shrink-0 transition-transform"
                :class="{ '-rotate-90': !open }"
            />
            <span class="text-highlighted shrink-0 font-semibold">
                {{ title }}
                <span v-if="required" class="text-error">*</span>
            </span>
            <span
                v-if="subtitle"
                class="min-w-0 flex-1 truncate text-sm"
                :class="!open && error ? 'text-error' : 'text-muted'"
            >
                {{ subtitle }}
            </span>
        </button>

        <div
            v-show="open"
            :id="bodyId"
            class="border-default border-t p-4 sm:p-6"
        >
            <slot />
        </div>
    </section>
</template>
