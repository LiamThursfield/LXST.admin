<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { TableColumn } from '@nuxt/ui';
import { index } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/HistoryController';
import { formatDateTime } from '@/lib/timezone';
import type { HistorySessionData, PlayedTrackData } from '@/types';

const props = defineProps<{
    session: { data: HistorySessionData };
    plays: { data: PlayedTrackData[] };
    timezone: string;
}>();

const { trans } = useTrans();
const session = computed(() => props.session.data);

const tab = ref('plays');

const tabs = computed(() => [
    {
        value: 'plays',
        label: trans('dj.history.show.plays'),
        count: session.value.plays_count,
    },
]);

const breadcrumbs = computed(() => [
    { label: trans('dj.history.show.back'), to: index.url() },
    { label: session.value.name },
]);

const columns: TableColumn<PlayedTrackData>[] = [
    { id: 'title', header: trans('dj.record.columns.track') },
    { id: 'played_at', header: trans('dj.record.columns.played_at') },
];

const sources: Record<HistorySessionData['source'], string> = {
    import: trans('dj.history.show.imported'),
    manual: trans('dj.history.manual_session'),
    cli: trans('dj.history.live_session'),
};

function formatTime(value: string | null): string | null {
    return value ? formatDateTime(value, props.timezone) : null;
}

const details = computed(() => [
    {
        label: trans('dj.history.show.gig'),
        value: session.value.gig?.name ?? trans('dj.history.not_linked'),
        href: session.value.gig?.url,
    },
    {
        label: trans('dj.history.show.stats'),
        value: session.value.include_in_stats
            ? trans('dj.history.in_stats')
            : trans('dj.history.not_in_stats'),
        color: session.value.include_in_stats ? 'success' : 'neutral',
    } as const,
    {
        label: trans('dj.history.show.source'),
        value: sources[session.value.source],
    },
    { label: trans('dj.history.show.library'), value: session.value.library },
    {
        label: trans('dj.history.show.started_at'),
        value: formatTime(session.value.started_at),
    },
    {
        label: trans('dj.history.show.ended_at'),
        value: formatTime(session.value.ended_at),
    },
]);
</script>

<template>
    <RecordLayout
        v-model:tab="tab"
        :title="session.name"
        :breadcrumbs="breadcrumbs"
        :tabs="tabs"
    >
        <template v-if="session.is_superseded" #badges>
            <UBadge color="neutral" variant="subtle">
                {{ trans('dj.history.superseded') }}
            </UBadge>
        </template>

        <template v-if="session.is_superseded" #notice>
            <UAlert
                color="neutral"
                variant="subtle"
                icon="i-lucide-info"
                :description="trans('dj.history.superseded_help')"
            />
        </template>

        <RecordTable
            :columns="columns"
            :rows="plays.data"
            :empty-title="trans('dj.history.show.no_plays')"
            empty-icon="i-lucide-history"
        >
            <template #title-cell="{ row }">
                <div class="flex flex-col">
                    <Link
                        v-if="row.original.track_url ?? row.original.song_url"
                        :href="
                            (row.original.track_url ??
                                row.original.song_url) as string
                        "
                        class="text-highlighted font-medium hover:underline"
                    >
                        {{ row.original.title }}
                    </Link>
                    <span v-else class="text-highlighted font-medium">
                        {{ row.original.title }}
                    </span>
                    <span class="text-muted text-xs">
                        {{ row.original.artist }}
                    </span>
                </div>
            </template>
            <template #played_at-cell="{ row }">
                {{ formatTime(row.original.played_at) }}
            </template>
        </RecordTable>

        <template #sidebar>
            <RecordFacts
                :title="trans('common.record.details')"
                :facts="details"
            />
        </template>
    </RecordLayout>
</template>
