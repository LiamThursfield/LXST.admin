<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { DropdownMenuItem, TableColumn } from '@nuxt/ui';
import {
    hide,
    verify,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/CanonicalTrackController';
import { index } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/LibraryTrackController';
import { store as split } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/LibraryTrackSplitController';
import { show as showSong } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/SongController';
import { formatDateTime, userTimezone } from '@/lib/timezone';
import CanonicalMergeModal from '@/tenant/components/dj/CanonicalMergeModal.vue';
import RequestVisibilityCard from '@/tenant/components/dj/RequestVisibilityCard.vue';
import type { LibraryTrackDetailData } from '@/types';

type Play = LibraryTrackDetailData['history_plays'][number];
type OtherSource = LibraryTrackDetailData['other_sources'][number];

const props = defineProps<{
    track: { data: LibraryTrackDetailData };
    canManageMatching: boolean;
}>();

const detail = computed(() => props.track.data);

const { trans } = useTrans();
const timezone = userTimezone(useAuth().value.user);

const tab = ref('history');

const tabs = computed(() => [
    {
        value: 'history',
        label: trans('dj.tracks.show.history'),
        count: detail.value.history_plays_count,
    },
    {
        value: 'other_sources',
        label: trans('dj.tracks.show.other_sources'),
        count: detail.value.other_sources.length,
    },
]);

const breadcrumbs = computed(() => [
    { label: trans('dj.tracks.show.back'), to: index.url() },
    { label: detail.value.title },
]);

const playColumns: TableColumn<Play>[] = [
    { id: 'played_at', header: trans('dj.record.columns.played_at') },
    { accessorKey: 'session', header: trans('dj.record.columns.session') },
];

const otherSourceColumns: TableColumn<OtherSource>[] = [
    { id: 'title', header: trans('dj.record.columns.track') },
    { accessorKey: 'library', header: trans('dj.record.columns.library') },
    { accessorKey: 'source', header: trans('dj.record.columns.source') },
];

const details = computed(() => [
    ...(
        [
            ['album', detail.value.album],
            ['label', detail.value.label],
            ['year', detail.value.year],
            ['genre', detail.value.genre],
            ['bpm', detail.value.bpm],
        ] as const
    ).map(([key, value]) => ({
        label: trans(`dj.tracks.show.${key}`),
        value,
    })),
    {
        label: trans('dj.tracks.show.key'),
        value: detail.value.key
            ? trans('dj.tracks.show.key_notations', {
                  camelot: detail.value.key.camelot,
                  open_key: detail.value.key.open_key,
                  standard: detail.value.key.standard,
              })
            : null,
    },
    ...(
        [
            ['duration', detail.value.duration],
            [
                'rating',
                detail.value.rating ? '★'.repeat(detail.value.rating) : null,
            ],
        ] as const
    ).map(([key, value]) => ({
        label: trans(`dj.tracks.show.${key}`),
        value,
    })),
]);

const source = computed(() => [
    ...(
        [
            ['library', detail.value.library],
            ['service', detail.value.service],
            ['date_added', detail.value.date_added],
            ['play_count', detail.value.play_count],
        ] as const
    ).map(([key, value]) => ({
        label: trans(`dj.tracks.show.${key}`),
        value,
    })),
    {
        label: trans('dj.tracks.show.source_id'),
        value: detail.value.source_track_id,
        mono: true,
    },
    {
        label: trans('dj.tracks.show.location'),
        value: detail.value.location,
        mono: true,
    },
]);

const song = computed(() => {
    const canonical = detail.value.canonical;

    if (!canonical) {
        return [];
    }

    return [
        {
            label: trans('dj.record.columns.song'),
            value: canonical.title,
            href: showSong.url(canonical.id),
        },
        {
            label: trans('dj.record.columns.artist'),
            value: canonical.artist ?? trans('dj.tracks.unknown_artist'),
        },
        { label: trans('dj.record.columns.bpm'), value: canonical.bpm },
        {
            label: trans('dj.record.columns.key'),
            value: canonical.key?.camelot,
        },
        {
            label: trans('dj.record.columns.duration'),
            value: canonical.duration,
        },
        {
            label: trans('dj.record.facts.status'),
            value: canonical.is_verified
                ? trans('dj.tracks.show.verified')
                : trans('dj.tracks.show.unverified'),
            color: canonical.is_verified ? 'success' : 'neutral',
        } as const,
    ];
});

const busy = ref(false);
const splitOpen = ref(false);
const mergeOpen = ref(false);

function setVerified(verified: boolean) {
    if (!detail.value.canonical) {
        return;
    }

    router.patch(
        verify.url(detail.value.canonical.id),
        { verified },
        {
            preserveScroll: true,
            onStart: () => (busy.value = true),
            onFinish: () => (busy.value = false),
        },
    );
}

function splitOff() {
    router.post(
        split.url(detail.value.id),
        {},
        {
            preserveScroll: true,
            onStart: () => (busy.value = true),
            onFinish: () => {
                busy.value = false;
                splitOpen.value = false;
            },
        },
    );
}

const menu = computed<DropdownMenuItem[]>(() => {
    const canonical = detail.value.canonical;

    if (!props.canManageMatching || !canonical) {
        return [];
    }

    return [
        {
            label: canonical.is_verified
                ? trans('dj.matching.track.unverify')
                : trans('dj.matching.track.verify'),
            icon: canonical.is_verified
                ? 'i-lucide-shield-off'
                : 'i-lucide-shield-check',
            disabled: busy.value,
            onSelect: () => setVerified(!canonical.is_verified),
        },
        {
            label: trans('dj.matching.track.merge'),
            icon: 'i-lucide-merge',
            disabled: busy.value,
            onSelect: () => (mergeOpen.value = true),
        },
        {
            label: trans('dj.matching.track.split'),
            icon: 'i-lucide-split',
            disabled: busy.value,
            onSelect: () => (splitOpen.value = true),
        },
    ];
});
</script>

<template>
    <RecordLayout
        v-model:tab="tab"
        :title="detail.title"
        :breadcrumbs="breadcrumbs"
        :subtitle="detail.artist?.name ?? trans('dj.tracks.unknown_artist')"
        :tabs="tabs"
        :menu="menu"
    >
        <template v-if="detail.key || detail.missing_since" #badges>
            <UBadge v-if="detail.key" color="primary" variant="subtle">
                {{ detail.key.label }}
            </UBadge>
            <UBadge
                v-if="detail.missing_since"
                color="warning"
                variant="subtle"
            >
                {{ trans('dj.tracks.missing') }}
            </UBadge>
        </template>

        <template v-if="detail.missing_since" #notice>
            <UAlert
                color="warning"
                variant="subtle"
                icon="i-lucide-circle-alert"
                :description="
                    trans('dj.tracks.show.missing', {
                        date: formatDateTime(detail.missing_since, timezone),
                    })
                "
            />
        </template>

        <RecordTable
            v-if="tab === 'history'"
            :columns="playColumns"
            :rows="detail.history_plays"
            :total="detail.history_plays_count"
            :empty-title="trans('dj.tracks.show.no_history')"
            empty-icon="i-lucide-history"
        >
            <template #played_at-cell="{ row }">
                {{
                    row.original.played_at
                        ? formatDateTime(row.original.played_at, timezone)
                        : ''
                }}
            </template>
        </RecordTable>

        <RecordTable
            v-else
            :columns="otherSourceColumns"
            :rows="detail.other_sources"
            :empty-title="trans('dj.tracks.show.no_other_sources')"
            empty-icon="i-lucide-library"
        >
            <template #title-cell="{ row }">
                <span class="flex items-center gap-2">
                    <Link
                        :href="row.original.url"
                        class="text-highlighted font-medium hover:underline"
                    >
                        {{ row.original.title }}
                    </Link>
                    <UBadge
                        v-if="row.original.is_missing"
                        color="warning"
                        variant="subtle"
                        size="sm"
                    >
                        {{ trans('dj.tracks.missing') }}
                    </UBadge>
                </span>
            </template>
        </RecordTable>

        <UModal
            v-model:open="splitOpen"
            :title="trans('dj.matching.track.split')"
        >
            <template #body>
                <p class="text-sm">
                    {{ trans('dj.matching.track.confirm_split') }}
                </p>
            </template>
            <template #footer>
                <div class="flex w-full justify-end gap-2">
                    <UButton
                        color="neutral"
                        variant="ghost"
                        :label="trans('common.actions.cancel')"
                        @click="splitOpen = false"
                    />
                    <UButton
                        :loading="busy"
                        :label="trans('dj.matching.track.split')"
                        @click="splitOff"
                    />
                </div>
            </template>
        </UModal>

        <CanonicalMergeModal
            v-if="detail.canonical"
            v-model:open="mergeOpen"
            record="track"
            :source-id="detail.canonical.id"
            :initial-search="detail.canonical.title"
            :title="trans('dj.matching.track.merge_title')"
            :description="trans('dj.matching.track.merge_help')"
            :placeholder="trans('dj.matching.track.merge_search')"
        />

        <template #sidebar>
            <RecordFacts
                v-if="detail.canonical"
                :title="trans('dj.tracks.show.canonical')"
                :facts="song"
            >
                <p class="text-muted mt-3 text-xs">
                    {{ trans('dj.tracks.show.canonical_help') }}
                </p>
            </RecordFacts>
            <UCard v-else variant="subtle">
                <h2 class="text-highlighted mb-1 text-sm font-semibold">
                    {{ trans('dj.tracks.show.canonical') }}
                </h2>
                <p class="text-muted text-sm">
                    {{ trans('dj.tracks.show.no_canonical') }}
                </p>
            </UCard>

            <RecordFacts
                :title="trans('dj.tracks.show.details')"
                :facts="details"
            />

            <RecordFacts
                :title="trans('dj.tracks.show.library')"
                :facts="source"
            />

            <UCard
                v-if="detail.tags.length || detail.comments"
                variant="subtle"
            >
                <div class="flex flex-col gap-4">
                    <div v-if="detail.tags.length">
                        <h2 class="text-highlighted mb-2 text-sm font-semibold">
                            {{ trans('dj.tracks.show.tags') }}
                        </h2>
                        <div class="flex flex-wrap gap-1.5">
                            <UBadge
                                v-for="tag in detail.tags"
                                :key="`${tag.category}-${tag.name}`"
                                color="neutral"
                                variant="subtle"
                            >
                                {{
                                    tag.category
                                        ? `${tag.category}: ${tag.name}`
                                        : tag.name
                                }}
                            </UBadge>
                        </div>
                    </div>
                    <div v-if="detail.comments">
                        <h2 class="text-highlighted mb-2 text-sm font-semibold">
                            {{ trans('dj.tracks.show.comments') }}
                        </h2>
                        <p class="whitespace-pre-line text-sm">
                            {{ detail.comments }}
                        </p>
                    </div>
                </div>
            </UCard>

            <RequestVisibilityCard
                v-if="detail.canonical"
                :hidden="detail.canonical.is_hidden_from_requests"
                :hidden-with-artist="
                    detail.canonical.is_artist_hidden_from_requests
                "
                :help="trans('dj.requests_visibility.song_help')"
                :url="hide.url(detail.canonical.id)"
                :can-manage="canManageMatching"
            />
        </template>
    </RecordLayout>
</template>
