<script setup lang="ts" generic="T">
import type { TableColumn } from '@nuxt/ui';
// Imported explicitly: the central app doesn't use this yet, so its generated component types lack it.
import UEmpty from '@nuxt/ui/components/Empty.vue';

/**
 * A record page's tab of related records: a table in a card, an empty state, and, when only some
 * are shown, how many and a link to see them all. Cell slots (`{column}-cell`) pass through to
 * the table.
 */
const props = withDefaults(
    defineProps<{
        columns: TableColumn<T>[];
        rows: T[];
        emptyTitle: string;
        emptyIcon?: string;
        /** How many there are, when `rows` is only some of them. */
        total?: number | null;
        viewAllHref?: string | null;
        viewAllLabel?: string;
    }>(),
    {
        emptyIcon: 'i-lucide-inbox',
        total: null,
        viewAllHref: null,
        viewAllLabel: undefined,
    },
);

const { trans } = useTrans();

const isPartial = computed(
    () => props.total !== null && props.total > props.rows.length,
);
</script>

<template>
    <UCard variant="outline" :ui="{ body: 'p-0 sm:p-0' }">
        <UEmpty
            v-if="!rows.length"
            :icon="emptyIcon"
            :title="emptyTitle"
            variant="naked"
            class="py-12"
        />

        <template v-else>
            <UTable :data="rows" :columns="columns" class="w-full">
                <template
                    v-for="(_, name) in $slots"
                    :key="name"
                    #[name]="slotProps"
                >
                    <slot :name="name" v-bind="slotProps ?? {}" />
                </template>
            </UTable>

            <div
                v-if="isPartial || viewAllHref"
                class="border-default flex flex-wrap items-center justify-between gap-2 border-t px-4 py-2.5 text-sm"
            >
                <span class="text-muted">
                    <template v-if="isPartial">
                        {{
                            trans('common.record.showing', {
                                shown: rows.length,
                                total: total ?? rows.length,
                            })
                        }}
                    </template>
                </span>
                <UButton
                    v-if="viewAllHref"
                    as="Link"
                    :href="viewAllHref"
                    variant="link"
                    class="px-0"
                    trailing-icon="i-lucide-arrow-right"
                    :label="viewAllLabel ?? trans('common.record.view_all')"
                />
            </div>
        </template>
    </UCard>
</template>
