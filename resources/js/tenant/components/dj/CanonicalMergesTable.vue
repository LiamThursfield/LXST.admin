<script setup lang="ts">
import type { TableColumn } from '@nuxt/ui';
import { formatDateTime, userTimezone } from '@/lib/timezone';
import type { CanonicalMergeData } from '@/types';

/** A song's or artist's merges and splits, for its record page. */
defineProps<{
    merges: CanonicalMergeData[];
}>();

const { trans } = useTrans();
const timezone = userTimezone(useAuth().value.user);

const columns: TableColumn<CanonicalMergeData>[] = [
    { id: 'change', header: trans('dj.record.columns.change') },
    { id: 'by', header: trans('dj.record.columns.by') },
    { id: 'when', header: trans('dj.record.columns.when') },
];
</script>

<template>
    <RecordTable
        :columns="columns"
        :rows="merges"
        :empty-title="trans('dj.songs.show.no_merges')"
        empty-icon="i-lucide-merge"
    >
        <template #change-cell="{ row }">
            <span class="whitespace-normal">
                {{
                    trans(`dj.matching.history_entry.${row.original.action}`, {
                        source: row.original.source_name,
                        target: row.original.target_name,
                        count: row.original.moved_count,
                    })
                }}
            </span>
        </template>
        <template #by-cell="{ row }">
            {{ row.original.user ?? trans('dj.matching.automatic') }}
        </template>
        <template #when-cell="{ row }">
            {{ formatDateTime(row.original.created_at, timezone) }}
        </template>
    </RecordTable>
</template>
