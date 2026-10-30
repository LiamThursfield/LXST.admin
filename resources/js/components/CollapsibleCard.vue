<script setup lang="ts">
/**
 * A card of form fields that can be collapsed, for parts of a long form that are rarely changed.
 * Open, it shows the description; collapsed, a one-line summary of what's in it (e.g. "3 links").
 * Collapsed fields stay mounted, just hidden, and the card opens when something in it has an error.
 */
const props = withDefaults(
    defineProps<{
        title: string;
        description?: string | null;
        summary?: string | null;
        /** Whether something in it has an error, which opens it. */
        hasError?: boolean;
        defaultOpen?: boolean;
    }>(),
    {
        description: null,
        summary: null,
        hasError: false,
        defaultOpen: true,
    },
);

const isOpen = ref(props.defaultOpen);

function toggle() {
    isOpen.value = !isOpen.value;
}

watch(
    () => props.hasError,
    (hasError) => {
        if (hasError) {
            isOpen.value = true;
        }
    },
);

const bodyId = useId();
</script>

<template>
    <section
        class="bg-elevated/50 rounded-lg ring"
        :class="hasError ? 'ring-error' : 'ring-default'"
    >
        <button
            type="button"
            class="focus-visible:outline-primary flex w-full items-center gap-2.5 rounded-lg px-4 py-3.5 text-left focus-visible:outline-2 sm:px-6"
            :aria-expanded="isOpen"
            :aria-controls="bodyId"
            @click="toggle"
        >
            <UIcon
                name="i-lucide-chevron-down"
                class="text-muted size-4 shrink-0 transition-transform"
                :class="{ '-rotate-90': !isOpen }"
            />
            <span class="text-highlighted shrink-0 font-semibold">
                {{ title }}
            </span>
            <span
                v-if="isOpen ? description : summary"
                class="text-muted min-w-0 flex-1 truncate text-sm"
            >
                {{ isOpen ? description : summary }}
            </span>
        </button>

        <div
            v-show="isOpen"
            :id="bodyId"
            class="border-default border-t p-4 sm:p-6"
        >
            <slot />
        </div>
    </section>
</template>
