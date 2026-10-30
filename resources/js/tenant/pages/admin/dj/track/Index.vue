<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
// Imported explicitly, as the generated component types don't include UTooltip
import UTooltip from '@nuxt/ui/components/Tooltip.vue';
import {
    hide as hideTracks,
    verify as verifyTracks,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/LibraryTrackBulkController';
import { index } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/LibraryTrackController';
import BulkAssignModal from '@/tenant/components/dj/BulkAssignModal.vue';
import type { LibraryTrackData, SelectItem } from '@/types';

type TrackFilter = {
    search?: string;
    library_id?: string;
    artist_id?: string;
    genre?: string;
    key?: string;
    compatible?: boolean | string;
    bpm_min?: string;
    bpm_max?: string;
    duration_min?: string;
    duration_max?: string;
    missing?: boolean | string;
    hidden?: boolean | string;
};

const props = defineProps<{
    filter: TrackFilter;
    sort: string;
    columns: Array<{ accessorKey: string; header: string }>;
    tracks: {
        data: LibraryTrackData[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
    libraries: SelectItem[];
    genres: SelectItem[];
    keys: SelectItem[];
    artist: string | null;
    canManageMatching: boolean;
}>();

const { trans } = useTrans();

const isTrue = (value: unknown) =>
    value === true || value === '1' || value === 'true';

const sortField = ref(props.sort.replace(/^-/, ''));
const sortDescending = ref(props.sort.startsWith('-'));

const showMoreFilters = ref(
    Boolean(
        props.filter.genre ||
        props.filter.bpm_min ||
        props.filter.bpm_max ||
        props.filter.duration_min ||
        props.filter.duration_max ||
        isTrue(props.filter.missing) ||
        isTrue(props.filter.hidden),
    ),
);

const sortItems = [
    'title',
    'artist',
    'bpm',
    'key',
    'duration',
    'date_added',
    'play_count',
].map((value) => ({ value, label: trans(`dj.tracks.sorts.${value}`) }));

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => index.url(),
    initial: {
        search: props.filter.search ?? '',
        library_id: props.filter.library_id
            ? String(props.filter.library_id)
            : '',
        artist_id: props.filter.artist_id ? String(props.filter.artist_id) : '',
        genre: props.filter.genre ?? '',
        key: props.filter.key ?? '',
        compatible: isTrue(props.filter.compatible),
        bpm_min: props.filter.bpm_min ? String(props.filter.bpm_min) : '',
        bpm_max: props.filter.bpm_max ? String(props.filter.bpm_max) : '',
        duration_min: props.filter.duration_min
            ? String(props.filter.duration_min)
            : '',
        duration_max: props.filter.duration_max
            ? String(props.filter.duration_max)
            : '',
        missing: isTrue(props.filter.missing),
        hidden: isTrue(props.filter.hidden),
    },
    labels: {
        library_id: {
            label: trans('dj.tracks.filters.library'),
            items: () => props.libraries,
        },
        // Filtering by artist comes from the artist's page
        artist_id: {
            label: trans('dj.tracks.columns.artist'),
            format: () =>
                trans('dj.tracks.filters.artist', { name: props.artist ?? '' }),
        },
        genre: {
            label: trans('dj.tracks.filters.genre'),
            items: () => props.genres,
        },
        key: { label: trans('dj.tracks.filters.key'), items: () => props.keys },
        compatible: { label: trans('dj.tracks.filters.compatible') },
        bpm_min: { label: trans('dj.tracks.filters.bpm_min') },
        bpm_max: { label: trans('dj.tracks.filters.bpm_max') },
        duration_min: { label: trans('dj.tracks.filters.duration_min') },
        duration_max: { label: trans('dj.tracks.filters.duration_max') },
        missing: { label: trans('dj.tracks.filters.missing') },
        hidden: { label: trans('dj.requests_visibility.filter') },
    },
    extra: () => ({
        sort: `${sortDescending.value ? '-' : ''}${sortField.value}`,
    }),
});

// Keys that mix well only mean something alongside a key
watch(
    () => filters.value.key,
    (key) => {
        if (!key) {
            filters.value.compatible = false;
        }
    },
);

// Rows ticked on this page, for the bulk actions
const selected = ref<Array<string | number>>([]);
const assignOpen = ref(false);

const selectedIds = computed(() => selected.value.map(Number));

function bulkVerify(verified: boolean) {
    router.post(
        verifyTracks.url(),
        { track_ids: selectedIds.value, verified },
        { preserveScroll: true, onSuccess: () => (selected.value = []) },
    );
}

function bulkHide(hidden: boolean) {
    router.post(
        hideTracks.url(),
        { track_ids: selectedIds.value, hidden },
        { preserveScroll: true, onSuccess: () => (selected.value = []) },
    );
}

const selectedTracks = computed(() =>
    props.tracks.data.filter((track) => selectedIds.value.includes(track.id)),
);
const selectedArtistCount = computed(
    () =>
        new Set(selectedTracks.value.map((track) => track.artist?.id ?? 0))
            .size,
);
</script>

<template>
    <ListLayout
        v-model:search="filters.search"
        v-model:selected="selected"
        :title="trans('dj.tracks.title')"
        :columns="columns"
        link-column="title"
        :rows="tracks.data"
        :pagination="tracks.meta"
        :search-placeholder="trans('dj.tracks.filters.search')"
        :chips="chips"
        :has-filters="hasFilters"
        :selectable="canManageMatching"
        :empty-title="trans('dj.tracks.empty')"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template #filters>
            <USelect
                v-model="filters.library_id"
                class="w-full sm:w-44"
                :items="libraries"
                :placeholder="trans('dj.tracks.filters.library')"
            />
            <!-- Kept together, as the checkbox only applies to the key -->
            <div class="flex w-full items-center gap-3 sm:w-auto">
                <USelect
                    v-model="filters.key"
                    class="w-36 shrink-0"
                    :items="keys"
                    :placeholder="trans('dj.tracks.filters.key')"
                />
                <UTooltip :text="trans('dj.tracks.filters.compatible_help')">
                    <UCheckbox
                        v-model="filters.compatible"
                        :disabled="!filters.key"
                        :label="trans('dj.tracks.filters.compatible')"
                    />
                </UTooltip>
            </div>
            <UButton
                color="neutral"
                variant="ghost"
                size="sm"
                icon="i-lucide-sliders-horizontal"
                :label="trans('dj.tracks.filters.more')"
                @click="showMoreFilters = !showMoreFilters"
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

        <template v-if="showMoreFilters" #notice>
            <div
                v-if="showMoreFilters"
                class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4"
            >
                <UFormField :label="trans('dj.tracks.filters.genre')">
                    <USelect
                        v-model="filters.genre"
                        class="w-full"
                        :items="genres"
                        :placeholder="trans('dj.tracks.filters.genre')"
                    />
                </UFormField>
                <UFormField :label="trans('dj.tracks.filters.bpm')">
                    <div class="flex items-center gap-2">
                        <UInput
                            v-model="filters.bpm_min"
                            type="number"
                            min="0"
                            :placeholder="trans('dj.tracks.filters.bpm_min')"
                        />
                        <UInput
                            v-model="filters.bpm_max"
                            type="number"
                            min="0"
                            :placeholder="trans('dj.tracks.filters.bpm_max')"
                        />
                    </div>
                </UFormField>
                <UFormField :label="trans('dj.tracks.filters.duration')">
                    <div class="flex items-center gap-2">
                        <UInput
                            v-model="filters.duration_min"
                            type="number"
                            min="0"
                            :placeholder="
                                trans('dj.tracks.filters.duration_min')
                            "
                        />
                        <UInput
                            v-model="filters.duration_max"
                            type="number"
                            min="0"
                            :placeholder="
                                trans('dj.tracks.filters.duration_max')
                            "
                        />
                    </div>
                </UFormField>
                <UFormField label="&nbsp;">
                    <USwitch
                        v-model="filters.missing"
                        :label="trans('dj.tracks.filters.missing')"
                    />
                </UFormField>
                <UFormField label="&nbsp;">
                    <USwitch
                        v-model="filters.hidden"
                        :label="trans('dj.requests_visibility.filter')"
                    />
                </UFormField>
            </div>
        </template>

        <template #bulk-actions>
            <UButton
                size="sm"
                icon="i-lucide-link"
                :label="trans('dj.bulk.assign_song')"
                @click="assignOpen = true"
            />
            <UButton
                size="sm"
                color="neutral"
                variant="outline"
                icon="i-lucide-shield-check"
                :label="trans('dj.bulk.verify')"
                @click="bulkVerify(true)"
            />
            <UButton
                size="sm"
                color="neutral"
                variant="outline"
                icon="i-lucide-shield-off"
                :label="trans('dj.bulk.unverify')"
                @click="bulkVerify(false)"
            />
            <UButton
                size="sm"
                color="neutral"
                variant="outline"
                icon="i-lucide-eye-off"
                :label="trans('dj.requests_visibility.hide')"
                @click="bulkHide(true)"
            />
            <UButton
                size="sm"
                color="neutral"
                variant="outline"
                icon="i-lucide-eye"
                :label="trans('dj.requests_visibility.show')"
                @click="bulkHide(false)"
            />
        </template>

        <template #title-cell="{ row }">
            <div class="flex min-w-48 flex-col">
                <Link
                    :href="row.original.url"
                    class="text-highlighted font-medium hover:underline"
                >
                    {{ row.original.title }}
                </Link>
                <span class="text-muted flex items-center gap-1 text-xs">
                    {{
                        row.original.artist?.name ??
                        trans('dj.tracks.unknown_artist')
                    }}
                    <UTooltip
                        v-if="row.original.is_missing"
                        :text="trans('dj.tracks.missing_help')"
                    >
                        <UBadge color="warning" variant="subtle" size="sm">
                            {{ trans('dj.tracks.missing') }}
                        </UBadge>
                    </UTooltip>
                    <UBadge
                        v-if="row.original.is_hidden_from_requests"
                        color="neutral"
                        variant="subtle"
                        size="sm"
                        icon="i-lucide-eye-off"
                    >
                        {{ trans('dj.requests_visibility.hidden') }}
                    </UBadge>
                </span>
            </div>
        </template>

        <template #bpm-cell="{ row }">
            {{ row.original.bpm ?? '' }}
        </template>

        <template #key-cell="{ row }">
            <UTooltip v-if="row.original.key" :text="row.original.key.standard">
                <UBadge color="neutral" variant="subtle">
                    {{ row.original.key.camelot }}
                </UBadge>
            </UTooltip>
        </template>

        <BulkAssignModal
            v-model:open="assignOpen"
            record="track"
            :ids="selectedIds"
            :default-name="selectedTracks[0]?.title ?? ''"
            :artist-count="selectedArtistCount"
            @assigned="selected = []"
        />
    </ListLayout>
</template>
