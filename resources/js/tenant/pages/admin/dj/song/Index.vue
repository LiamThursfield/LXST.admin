<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    cleanup,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/SongController';
import type { SelectItem, SongData } from '@/types';

type SongFilter = {
    search?: string;
    artist_id?: string;
    verified?: string;
    shared?: boolean | string;
    orphaned?: boolean | string;
    hidden?: boolean | string;
    key?: string;
};

const props = defineProps<{
    filter: SongFilter;
    sort: string;
    columns: Array<{ accessorKey: string; header: string }>;
    songs: {
        data: SongData[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
    keys: SelectItem[];
    artist: string | null;
    orphanedCount: number;
    canManage: boolean;
}>();

const { trans } = useTrans();

const isTrue = (value: unknown) =>
    value === true || value === '1' || value === 'true';

const sortField = ref(props.sort.replace(/^-/, ''));
const sortDescending = ref(props.sort.startsWith('-'));

const sortItems = ['title', 'artist', 'bpm', 'library_tracks_count'].map(
    (value) => ({
        value,
        label: trans(`dj.songs.sorts.${value}`),
    }),
);

const verifiedItems = [
    { value: 'yes', label: trans('dj.songs.filters.verified_yes') },
    { value: 'no', label: trans('dj.songs.filters.verified_no') },
];

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => index.url(),
    initial: {
        search: props.filter.search ?? '',
        artist_id: props.filter.artist_id ? String(props.filter.artist_id) : '',
        verified: props.filter.verified ?? '',
        key: props.filter.key ?? '',
        shared: isTrue(props.filter.shared),
        orphaned: isTrue(props.filter.orphaned),
        hidden: isTrue(props.filter.hidden),
    },
    labels: {
        // Filtering by artist comes from the artist's page
        artist_id: {
            label: trans('dj.tracks.columns.artist'),
            format: () =>
                trans('dj.songs.filters.artist', { name: props.artist ?? '' }),
        },
        verified: {
            label: trans('dj.songs.filters.verified'),
            items: verifiedItems,
        },
        key: { label: trans('dj.tracks.filters.key'), items: () => props.keys },
        shared: { label: trans('dj.songs.filters.shared') },
        orphaned: { label: trans('dj.songs.filters.orphaned') },
        hidden: { label: trans('dj.requests_visibility.filter') },
    },
    extra: () => ({
        sort: `${sortDescending.value ? '-' : ''}${sortField.value}`,
    }),
});

const cleanupOpen = ref(false);
const cleaning = ref(false);

function runCleanup() {
    router.post(
        cleanup.url(),
        {},
        {
            preserveScroll: true,
            onStart: () => (cleaning.value = true),
            onFinish: () => {
                cleaning.value = false;
                cleanupOpen.value = false;
            },
        },
    );
}
</script>

<template>
    <ListLayout
        v-model:search="filters.search"
        :title="trans('dj.songs.title')"
        :columns="columns"
        link-column="title"
        :rows="songs.data"
        :pagination="songs.meta"
        :search-placeholder="trans('dj.songs.search')"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('dj.songs.empty')"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template #notice>
            <p class="text-muted text-sm">
                {{ trans('dj.songs.description') }}
            </p>

            <UAlert
                v-if="orphanedCount"
                color="warning"
                variant="subtle"
                icon="i-lucide-unlink"
                :description="
                    trans('dj.songs.orphaned_notice', {
                        count: orphanedCount,
                    })
                "
                :actions="
                    canManage
                        ? [
                              {
                                  label: trans('dj.songs.cleanup'),
                                  color: 'warning',
                                  variant: 'outline',
                                  onClick: () => (cleanupOpen = true),
                              },
                          ]
                        : []
                "
            />
        </template>

        <template #filters>
            <USelect
                v-model="filters.key"
                class="w-full sm:w-36"
                :items="keys"
                :placeholder="trans('dj.tracks.filters.key')"
            />
            <USelect
                v-model="filters.verified"
                class="w-full sm:w-40"
                :items="verifiedItems"
                :placeholder="trans('dj.songs.filters.verified')"
            />
            <UCheckbox
                v-model="filters.shared"
                :label="trans('dj.songs.filters.shared')"
            />
            <UCheckbox
                v-model="filters.orphaned"
                :label="trans('dj.songs.filters.orphaned')"
            />
            <UCheckbox
                v-model="filters.hidden"
                :label="trans('dj.requests_visibility.filter')"
            />
            <div class="ms-auto flex items-center gap-1">
                <span class="text-muted hidden text-sm sm:inline">
                    {{ trans('dj.tracks.filters.sort') }}
                </span>
                <USelect
                    v-model="sortField"
                    class="w-36"
                    size="sm"
                    :items="sortItems"
                    :aria-label="trans('dj.tracks.filters.sort')"
                />
                <UButton
                    color="neutral"
                    variant="ghost"
                    size="sm"
                    :icon="
                        sortDescending
                            ? 'i-lucide-arrow-down-wide-narrow'
                            : 'i-lucide-arrow-up-narrow-wide'
                    "
                    :aria-label="trans('dj.tracks.filters.sort')"
                    @click="sortDescending = !sortDescending"
                />
            </div>
        </template>

        <template #title-cell="{ row }">
            <div class="flex min-w-48 flex-col">
                <span class="flex items-center gap-1">
                    <Link
                        :href="row.original.url"
                        class="text-highlighted font-medium hover:underline"
                    >
                        {{ row.original.title }}
                    </Link>
                    <UIcon
                        v-if="row.original.is_verified"
                        name="i-lucide-badge-check"
                        class="text-success size-4"
                        :aria-label="trans('dj.matching.verified')"
                    />
                </span>
                <span class="text-muted text-xs">
                    {{
                        row.original.artist?.name ??
                        trans('dj.tracks.unknown_artist')
                    }}
                    <UBadge
                        v-if="
                            row.original.is_hidden_from_requests ||
                            row.original.is_artist_hidden_from_requests
                        "
                        color="neutral"
                        variant="subtle"
                        size="sm"
                        icon="i-lucide-eye-off"
                    >
                        {{
                            row.original.is_hidden_from_requests
                                ? trans('dj.requests_visibility.hidden')
                                : trans(
                                      'dj.requests_visibility.hidden_with_artist',
                                  )
                        }}
                    </UBadge>
                </span>
            </div>
        </template>

        <template #bpm-cell="{ row }">{{ row.original.bpm ?? '' }}</template>

        <template #key-cell="{ row }">
            <UBadge v-if="row.original.key" color="neutral" variant="subtle">
                {{ row.original.key.camelot }}
            </UBadge>
        </template>

        <template #library_tracks_count-cell="{ row }">
            <span>{{ row.original.library_tracks_count }}</span>
            <span
                v-if="row.original.libraries_count > 1"
                class="text-muted text-xs"
            >
                ·
                {{
                    trans('dj.songs.in_libraries', {
                        count: row.original.libraries_count,
                    })
                }}
            </span>
        </template>

        <UModal v-model:open="cleanupOpen" :title="trans('dj.songs.cleanup')">
            <template #body>
                <p class="text-sm">
                    {{ trans('dj.songs.confirm_cleanup') }}
                </p>
            </template>
            <template #footer>
                <div class="flex w-full justify-end gap-2">
                    <UButton
                        color="neutral"
                        variant="ghost"
                        :label="trans('common.actions.cancel')"
                        @click="cleanupOpen = false"
                    />
                    <UButton
                        color="warning"
                        :loading="cleaning"
                        :label="trans('dj.songs.cleanup')"
                        @click="runCleanup"
                    />
                </div>
            </template>
        </UModal>
    </ListLayout>
</template>
