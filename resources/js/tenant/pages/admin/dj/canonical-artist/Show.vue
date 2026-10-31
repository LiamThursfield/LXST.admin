<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { DropdownMenuItem, TableColumn } from '@nuxt/ui';
import {
    edit,
    hide,
    index,
    verify,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/CanonicalArtistController';
import CanonicalMergeModal from '@/tenant/components/dj/CanonicalMergeModal.vue';
import CanonicalMergesTable from '@/tenant/components/dj/CanonicalMergesTable.vue';
import RequestVisibilityCard from '@/tenant/components/dj/RequestVisibilityCard.vue';
import type { CanonicalMergeData } from '@/types';

type Song = {
    id: number;
    title: string;
    library_tracks_count: number;
    is_verified: boolean;
    url: string;
};

type LibraryArtist = {
    id: number;
    name: string;
    library: string;
    tracks_url: string;
};

const props = defineProps<{
    artist: {
        id: number;
        name: string;
        is_verified: boolean;
        is_hidden_from_requests: boolean;
        songs_url: string;
    };
    songs: Song[];
    songsCount: number;
    libraryArtists: LibraryArtist[];
    merges: { data: CanonicalMergeData[] };
    canManage: boolean;
}>();

const { trans } = useTrans();

const tab = ref('songs');

const tabs = computed(() => [
    {
        value: 'songs',
        label: trans('dj.canonical_artists.show.songs'),
        count: props.songsCount,
    },
    {
        value: 'library_artists',
        label: trans('dj.canonical_artists.show.library_artists'),
        count: props.libraryArtists.length,
    },
    {
        value: 'merges',
        label: trans('dj.songs.show.merges'),
        count: props.merges.data.length,
    },
]);

const breadcrumbs = computed(() => [
    { label: trans('dj.canonical_artists.title'), to: index.url() },
    { label: props.artist.name },
]);

const songColumns: TableColumn<Song>[] = [
    { id: 'title', header: trans('dj.record.columns.song') },
    {
        accessorKey: 'library_tracks_count',
        header: trans('dj.record.columns.library_tracks'),
    },
];

const libraryArtistColumns: TableColumn<LibraryArtist>[] = [
    { accessorKey: 'name', header: trans('dj.record.columns.artist') },
    { accessorKey: 'library', header: trans('dj.record.columns.library') },
    { id: 'tracks', header: '' },
];

const details = computed(() => [
    {
        label: trans('dj.canonical_artists.fields.name'),
        value: props.artist.name,
    },
    {
        label: trans('dj.record.facts.status'),
        value: props.artist.is_verified
            ? trans('dj.record.facts.verified')
            : trans('dj.record.facts.not_verified'),
        color: props.artist.is_verified ? 'success' : 'neutral',
    } as const,
    { label: trans('dj.record.facts.songs'), value: props.songsCount },
    {
        label: trans('dj.record.facts.library_artists'),
        value: props.libraryArtists.length,
    },
]);

const verifying = ref(false);

function setVerified(verified: boolean) {
    router.patch(
        verify.url(props.artist.id),
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
                  label: props.artist.is_verified
                      ? trans('dj.matching.track.unverify')
                      : trans('dj.matching.track.verify'),
                  icon: props.artist.is_verified
                      ? 'i-lucide-shield-off'
                      : 'i-lucide-shield-check',
                  disabled: verifying.value,
                  onSelect: () => setVerified(!props.artist.is_verified),
              },
              {
                  label: trans('dj.canonical_artists.show.merge'),
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
        :title="artist.name"
        :breadcrumbs="breadcrumbs"
        :tabs="tabs"
        :menu="menu"
        :edit-href="canManage ? edit.url(artist.id) : null"
    >
        <template v-if="artist.is_verified" #badges>
            <UBadge color="success" variant="subtle">
                {{ trans('dj.matching.verified') }}
            </UBadge>
        </template>

        <RecordTable
            v-if="tab === 'songs'"
            :columns="songColumns"
            :rows="songs"
            :total="songsCount"
            :view-all-href="songsCount > songs.length ? artist.songs_url : null"
            :view-all-label="
                trans('dj.canonical_artists.show.all_songs', {
                    count: songsCount,
                })
            "
            :empty-title="trans('dj.canonical_artists.show.no_songs')"
            empty-icon="i-lucide-music"
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
                        v-if="row.original.is_verified"
                        color="success"
                        variant="subtle"
                        size="sm"
                    >
                        {{ trans('dj.matching.verified') }}
                    </UBadge>
                </span>
            </template>
        </RecordTable>

        <RecordTable
            v-else-if="tab === 'library_artists'"
            :columns="libraryArtistColumns"
            :rows="libraryArtists"
            :empty-title="trans('dj.canonical_artists.show.no_library_artists')"
            empty-icon="i-lucide-library"
        >
            <template #tracks-cell="{ row }">
                <div class="text-end">
                    <UButton
                        as="Link"
                        :href="row.original.tracks_url"
                        variant="link"
                        size="sm"
                        trailing-icon="i-lucide-arrow-right"
                        :label="trans('dj.canonical_artists.show.view_tracks')"
                    />
                </div>
            </template>
        </RecordTable>

        <CanonicalMergesTable v-else :merges="merges.data" />

        <CanonicalMergeModal
            v-model:open="mergeOpen"
            record="artist"
            :source-id="artist.id"
            :initial-search="artist.name"
            :title="trans('dj.canonical_artists.show.merge_title')"
            :description="trans('dj.canonical_artists.show.merge_help')"
            :placeholder="trans('dj.canonical_artists.show.merge_search')"
        />

        <template #sidebar>
            <RecordFacts
                :title="trans('common.record.details')"
                :facts="details"
            />
            <RequestVisibilityCard
                :hidden="artist.is_hidden_from_requests"
                :help="trans('dj.requests_visibility.artist_help')"
                :url="hide.url(artist.id)"
                :can-manage="canManage"
            />
        </template>
    </RecordLayout>
</template>
