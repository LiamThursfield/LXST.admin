<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { BadgeProps } from '@nuxt/ui';

/** One fact about a record: blank values show a dash. */
export type RecordFact = {
    label: string;
    value?: string | number | null;
    /** Links the value, e.g. to a related record. */
    href?: string | null;
    /** Shows the value as a badge in this colour, e.g. a status. */
    color?: BadgeProps['color'];
    /** For codes and IDs. */
    mono?: boolean;
};

/**
 * A card of read-only facts about a record, as a definition list, for a record page's sidebar.
 * Anything else for the card, e.g. a button, goes in the default slot below the list.
 */
defineProps<{
    title: string;
    facts: RecordFact[];
}>();

defineSlots<{
    default?(): unknown;
}>();

const { trans } = useTrans();

function isBlank(value: RecordFact['value']): boolean {
    return value === null || value === undefined || value === '';
}
</script>

<template>
    <UCard variant="subtle">
        <h2 class="text-highlighted mb-3 text-sm font-semibold">
            {{ title }}
        </h2>

        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2.5 text-sm">
            <template v-for="fact in facts" :key="fact.label">
                <dt class="text-muted">{{ fact.label }}</dt>
                <dd
                    class="text-highlighted min-w-0 break-words text-end"
                    :class="{
                        'break-all font-mono text-xs leading-5': fact.mono,
                    }"
                >
                    <span v-if="isBlank(fact.value)" class="text-muted">
                        {{ trans('common.record.none') }}
                    </span>
                    <UBadge
                        v-else-if="fact.color"
                        :color="fact.color"
                        variant="subtle"
                    >
                        {{ fact.value }}
                    </UBadge>
                    <Link
                        v-else-if="fact.href"
                        :href="fact.href"
                        class="text-primary hover:underline"
                    >
                        {{ fact.value }}
                    </Link>
                    <template v-else>{{ fact.value }}</template>
                </dd>
            </template>
        </dl>

        <slot />
    </UCard>
</template>
