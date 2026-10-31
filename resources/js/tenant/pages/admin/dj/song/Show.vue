<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { DropdownMenuItem, TableColumn } from '@nuxt/ui';
import {
    hide,
    verify,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/CanonicalTrackController';
import {
    edit,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/SongController';
import { formatDateTime, userTimezone } from '@/lib/timezone';
import CanonicalMergeModal from '@/tenant/components/dj/CanonicalMergeModal.vue';
import CanonicalMergesTable from '@/tenant/components/dj/CanonicalMergesTable.vue';
import RequestVisibilityCard from '@/tenant/components/dj/RequestVisibilityCard.vue';
import type {
    CanonicalMergeData,
    LibraryTrackData,
    MusicalKeyData,
} from '@/types';

type Play = {
    session: string;
    library: string | null;
    played_at: string | null;
};

type Setlist = {
    id: number;
    name: string;
    gig: string | null;
    type: string | null;
    url: string;
};

const props = defineProps<{
    song: {
        id: number;
        title: string;
        artist: {
            id: number;
            name: string;
            url: string;
            is_hidden_from_requests: boolean;
        } | null;
        bpm: number | null;
        duration: string | null;
        key: MusicalKeyData | null;
        is_verified: boolean;
        is_hidden_from_requests: boolean;
        is_requestable: boolean;
    };
    libraryTracks: { data: LibraryTrackData[] };
    playsCount: number;
    plays: Play[];
    merges: { data: CanonicalMergeData[] };
    setlists: Setlist[];
    canManage: boolean;
}>();

const { trans } = useTrans();
const timezone = userTimezone(useAuth().value.user);

const tab = ref('tracks');

const tabs = computed(() => [
    {
        value: 'tracks',
        label: trans('dj.songs.show.library_tracks'),
        count: props.libraryTracks.data.length,
    },
    {
        value: 'history',
        label: trans('dj.songs.show.history'),
        count: props.playsCount,
    },
    {
        value: 'setlists',
        label: trans('dj.songs.show.setlists'),
        count: props.setlists.length,
    },
    {
        value: 'merges',
        label: trans('dj.songs.show.merges'),
        count: props.merges.data.length,
    },
]);

const breadcrumbs = computed(() => [
    { label: trans('dj.songs.title'), to: index.url() },
    { label: props.song.title },
]);

const trackColumns: TableColumn<LibraryTrackData>[] = [
    { id: 'title', header: trans('dj.record.columns.track') },
    { id: 'library', header: trans('dj.record.columns.library') },
    { accessorKey: 'bpm', header: trans('dj.record.columns.bpm') },
    { id: 'key', header: trans('dj.record.columns.key') },
    { accessorKey: 'duration', header: trans('dj.record.columns.duration') },
];

const playColumns: TableColumn<Play>[] = [
    { id: 'played_at', header: trans('dj.record.columns.played_at') },
    { accessorKey: 'session', header: trans('dj.record.columns.session') },
    { accessorKey: 'library', header: trans('dj.record.columns.library') },
];

const setlistColumns: TableColumn<Setlist>[] = [
    { id: 'name', header: trans('dj.record.columns.setlist') },
    { accessorKey: 'gig', header: trans('dj.record.columns.gig') },
    { accessorKey: 'type', header: trans('dj.record.columns.type') },
];

const details = computed(() => [
    {
        label: trans('dj.record.columns.artist'),
        value: props.song.artist?.name ?? trans('dj.tracks.unknown_artist'),
        href: props.song.artist?.url,
    },
    { label: trans('dj.record.columns.bpm'), value: props.song.bpm },
    { label: trans('dj.record.columns.key'), value: props.song.key?.label },
    {
        label: trans('dj.record.columns.duration'),
        value: props.song.duration,
    },
    {
        label: trans('dj.record.facts.status'),
        value: props.song.is_verified
            ? trans('dj.record.facts.verified')
            : trans('dj.record.facts.not_verified'),
        color: props.song.is_verified ? 'success' : 'neutral',
    } as const,
]);

const lastPlayed = computed(() => props.plays[0]?.played_at ?? null);

const activity = computed(() => [
    { label: trans('dj.record.facts.plays'), value: props.playsCount },
    {
        label: trans('dj.record.facts.last_played'),
        value: lastPlayed.value
            ? formatDateTime(lastPlayed.value, timezone)
            : null,
    },
    {
        label: trans('dj.record.facts.library_tracks'),
        value: props.libraryTracks.data.length,
    },
]);

const verifying = ref(false);

function setVerified(verified: boolean) {
    router.patch(
        verify.url(props.song.id),
        { verified },
        {
            preserveScroll: true,
            onStart: () => (verifying.value = true),
            onFinish: () => (verifying.value = false),
        },
    );
}

const mergeOpen = ref(false);

const menu = computed<DropdownMenuItem[]>(() =>
    props.canManage
        ? [
              {
                  label: props.song.is_verified
                      ? trans('dj.matching.track.unverify')
                      : trans('dj.matching.track.verify'),
                  icon: props.song.is_verified
                      ? 'i-lucide-shield-off'
                      : 'i-lucide-shield-check',
                  disabled: verifying.value,
                  onSelect: () => setVerified(!props.song.is_verified),
              },
              {
                  label: trans('dj.matching.track.merge'),
                  icon: 'i-lucide-merge',
                  onSelect: () => (mergeOpen.value = true),
              },
          ]
        : [],
);
</script>

<template>
    <RecordLayout
        v-model:tab="tab"
        :title="song.title"
        :breadcrumbs="breadcrumbs"
        :subtitle="song.artist?.name ?? trans('dj.tracks.unknown_artist')"
        :tabs="tabs"
        :menu="menu"
        :edit-href="canManage ? edit.url(song.id) : null"
    >
        <template v-if="song.is_verified" #badges>
            <UBadge color="success" variant="subtle">
                {{ trans('dj.matching.verified') }}
            </UBadge>
        </template>

        <RecordTable
            v-if="tab === 'tracks'"
            :columns="trackColumns"
            :rows="libraryTracks.data"
            :empty-title="trans('dj.songs.show.no_library_tracks')"
            empty-icon="i-lucide-library"
        >
            <template #title-cell="{ row }">
                <div class="flex flex-col">
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
                    <span class="text-muted text-xs">
                        {{
                            row.original.artist?.name ??
                            trans('dj.tracks.unknown_artist')
                        }}
                    </span>
                </div>
            </template>
            <template #library-cell="{ row }">
                {{ row.original.library }}
            </template>
            <template #key-cell="{ row }">
                {{ row.original.key?.camelot }}
            </template>
        </RecordTable>

        <RecordTable
            v-else-if="tab === 'history'"
            :columns="playColumns"
            :rows="plays"
            :total="playsCount"
            :empty-title="trans('dj.songs.show.no_history')"
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
            v-else-if="tab === 'setlists'"
            :columns="setlistColumns"
            :rows="setlists"
            :empty-title="trans('dj.songs.show.no_setlists')"
            empty-icon="i-lucide-list-music"
        >
            <template #name-cell="{ row }">
                <Link
                    :href="row.original.url"
                    class="text-highlighted font-medium hover:underline"
                >
                    {{ row.original.name }}
                </Link>
            </template>
        </RecordTable>

        <CanonicalMergesTable v-else :merges="merges.data" />

        <CanonicalMergeModal
            v-model:open="mergeOpen"
            record="track"
            :source-id="song.id"
            :initial-search="song.title"
            :title="trans('dj.matching.track.merge_title')"
            :description="trans('dj.matching.track.merge_help')"
            :placeholder="trans('dj.matching.track.merge_search')"
        />

        <template #sidebar>
            <RecordFacts
                :title="trans('common.record.details')"
                :facts="details"
            />
            <RecordFacts
                :title="trans('common.record.activity')"
                :facts="activity"
            />
            <RequestVisibilityCard
                :hidden="song.is_hidden_from_requests"
                :hidden-with-artist="song.artist?.is_hidden_from_requests"
                :requestable="song.is_requestable"
                :help="trans('dj.requests_visibility.song_help')"
                :url="hide.url(song.id)"
                :can-manage="canManage"
            />
        </template>
    </RecordLayout>
</template>
