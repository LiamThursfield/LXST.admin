<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
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

const filters = ref({
    search: props.filter.search ?? '',
    library_id: props.filter.library_id ? String(props.filter.library_id) : '',
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
});

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

const hasFilters = computed(() =>
    Object.entries(filters.value).some(([, value]) =>
        typeof value === 'boolean' ? value : value !== '',
    ),
);

const search = useDebounceFn((page: number = 1) => {
    const filter: Record<string, string> = {};

    for (const [name, value] of Object.entries(filters.value)) {
        if (typeof value === 'boolean') {
            if (value) {
                filter[name] = '1';
            }
        } else if (value !== '') {
            filter[name] = value;
        }
    }

    // Keys that mix well only mean something alongside a key
    if (!filter.key) {
        delete filter.compatible;
    }

    router.get(
        index.url(),
        {
            page,
            filter,
            sort: `${sortDescending.value ? '-' : ''}${sortField.value}`,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}, 300);

watch([filters, sortField, sortDescending], () => search(), { deep: true });

function clearFilters() {
    filters.value = {
        search: '',
        library_id: '',
        artist_id: '',
        genre: '',
        key: '',
        compatible: false,
        bpm_min: '',
        bpm_max: '',
        duration_min: '',
        duration_max: '',
        missing: false,
        hidden: false,
    };
}

// Rows ticked on this page, for the bulk actions
const selected = ref<number[]>([]);
const assignOpen = ref(false);

watch(
    () => props.tracks.data,
    () => (selected.value = []),
);

const allSelected = computed(
    () =>
        props.tracks.data.length > 0 &&
        props.tracks.data.every((row) => selected.value.includes(row.id)),
);

function toggleAll(checked: boolean | 'indeterminate') {
    selected.value =
        checked === true ? props.tracks.data.map((row) => row.id) : [];
}

function toggleRow(id: number, checked: boolean | 'indeterminate') {
    selected.value =
        checked === true
            ? [...new Set([...selected.value, id])]
            : selected.value.filter((selectedId) => selectedId !== id);
}

const tableColumns = computed(() =>
    props.canManageMatching
        ? [{ accessorKey: 'select', header: '' }, ...props.columns]
        : props.columns,
);

function bulkVerify(verified: boolean) {
    router.post(
        verifyTracks.url(),
        { track_ids: selected.value, verified },
        { preserveScroll: true, onSuccess: () => (selected.value = []) },
    );
}

function bulkHide(hidden: boolean) {
    router.post(
        hideTracks.url(),
        { track_ids: selected.value, hidden },
        { preserveScroll: true, onSuccess: () => (selected.value = []) },
    );
}

const selectedTracks = computed(() =>
    props.tracks.data.filter((track) => selected.value.includes(track.id)),
);
const selectedArtistCount = computed(
    () =>
        new Set(selectedTracks.value.map((track) => track.artist?.id ?? 0))
            .size,
);
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('dj.tracks.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>

                <template #right>
                    <span class="text-muted text-sm">
                        {{
                            trans('dj.tracks.total', {
                                count: tracks.meta.total,
                            })
                        }}
                    </span>
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('dj.tracks.title')" />

            <div class="flex flex-col gap-6">
                <div class="flex flex-col gap-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <UInput
                            v-model="filters.search"
                            class="w-full sm:w-auto sm:min-w-48 sm:max-w-80 sm:flex-1"
                            icon="i-lucide-search"
                            :placeholder="trans('dj.tracks.filters.search')"
                        />
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
                            <UTooltip
                                :text="
                                    trans('dj.tracks.filters.compatible_help')
                                "
                            >
                                <UCheckbox
                                    v-model="filters.compatible"
                                    :disabled="!filters.key"
                                    :label="
                                        trans('dj.tracks.filters.compatible')
                                    "
                                />
                            </UTooltip>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <UButton
                            color="neutral"
                            variant="ghost"
                            size="sm"
                            icon="i-lucide-sliders-horizontal"
                            :label="trans('dj.tracks.filters.more')"
                            @click="showMoreFilters = !showMoreFilters"
                        />
                        <UBadge
                            v-if="filters.artist_id && artist"
                            color="primary"
                            variant="subtle"
                            class="gap-1"
                        >
                            {{
                                trans('dj.tracks.filters.artist', {
                                    name: artist,
                                })
                            }}
                            <UButton
                                icon="i-lucide-x"
                                size="xs"
                                color="primary"
                                variant="link"
                                class="p-0"
                                :aria-label="trans('dj.tracks.filters.clear')"
                                @click="filters.artist_id = ''"
                            />
                        </UBadge>
                        <UButton
                            v-if="hasFilters"
                            color="neutral"
                            variant="link"
                            size="sm"
                            :label="trans('dj.tracks.filters.clear')"
                            @click="clearFilters"
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
                    </div>

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
                                    :placeholder="
                                        trans('dj.tracks.filters.bpm_min')
                                    "
                                />
                                <UInput
                                    v-model="filters.bpm_max"
                                    type="number"
                                    min="0"
                                    :placeholder="
                                        trans('dj.tracks.filters.bpm_max')
                                    "
                                />
                            </div>
                        </UFormField>
                        <UFormField
                            :label="trans('dj.tracks.filters.duration')"
                        >
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
                </div>

                <div
                    v-if="selected.length"
                    class="bg-elevated flex flex-wrap items-center gap-2 rounded-md px-3 py-2"
                >
                    <span class="text-sm font-medium">
                        {{
                            trans('dj.bulk.selected', {
                                count: selected.length,
                            })
                        }}
                    </span>
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
                    <UButton
                        size="sm"
                        color="neutral"
                        variant="ghost"
                        class="ms-auto"
                        :label="trans('dj.bulk.clear')"
                        @click="selected = []"
                    />
                </div>

                <UTable
                    :columns="tableColumns"
                    :data="tracks.data"
                    :empty="trans('dj.tracks.empty')"
                >
                    <template #select-header>
                        <UCheckbox
                            :model-value="
                                allSelected
                                    ? true
                                    : selected.length
                                      ? 'indeterminate'
                                      : false
                            "
                            :aria-label="trans('dj.bulk.select_all')"
                            @update:model-value="toggleAll"
                        />
                    </template>

                    <template #select-cell="{ row }">
                        <UCheckbox
                            :model-value="selected.includes(row.original.id)"
                            :aria-label="trans('dj.bulk.select_row')"
                            @update:model-value="
                                (checked) => toggleRow(row.original.id, checked)
                            "
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
                            <span
                                class="text-muted flex items-center gap-1 text-xs"
                            >
                                {{
                                    row.original.artist?.name ??
                                    trans('dj.tracks.unknown_artist')
                                }}
                                <UTooltip
                                    v-if="row.original.is_missing"
                                    :text="trans('dj.tracks.missing_help')"
                                >
                                    <UBadge
                                        color="warning"
                                        variant="subtle"
                                        size="sm"
                                    >
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
                        <UTooltip
                            v-if="row.original.key"
                            :text="row.original.key.standard"
                        >
                            <UBadge color="neutral" variant="subtle">
                                {{ row.original.key.camelot }}
                            </UBadge>
                        </UTooltip>
                    </template>
                </UTable>

                <div v-if="tracks.meta.last_page > 1" class="flex justify-end">
                    <UPagination
                        :items-per-page="tracks.meta.per_page"
                        :page="tracks.meta.current_page"
                        :total="tracks.meta.total"
                        @update:page="(page: number) => search(page)"
                    />
                </div>
            </div>

            <BulkAssignModal
                v-model:open="assignOpen"
                record="track"
                :ids="selected"
                :default-name="selectedTracks[0]?.title ?? ''"
                :artist-count="selectedArtistCount"
                @assigned="selected = []"
            />
        </template>
    </UDashboardPanel>
</template>
