<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    hide as hideArtists,
    verify as verifyArtists,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/LibraryArtistBulkController';
import { index } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/LibraryArtistController';
import BulkAssignModal from '@/tenant/components/dj/BulkAssignModal.vue';
import type { LibraryArtistData, SelectItem } from '@/types';

const props = defineProps<{
    filter: {
        search?: string;
        library_id?: string;
        hidden?: boolean | string;
    };
    sort: string;
    columns: Array<{ accessorKey: string; header: string }>;
    artists: {
        data: LibraryArtistData[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
    libraries: SelectItem[];
    canManageMatching: boolean;
}>();

const { trans } = useTrans();

const sortItems = [
    { value: 'name', label: trans('dj.artists.columns.name') },
    { value: '-tracks_count', label: trans('dj.artists.columns.tracks') },
];
const sort = ref(props.sort);

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => index.url(),
    initial: {
        search: props.filter.search ?? '',
        library_id: props.filter.library_id
            ? String(props.filter.library_id)
            : '',
        hidden: props.filter.hidden === true || props.filter.hidden === '1',
    },
    labels: {
        library_id: {
            label: trans('dj.artists.columns.library'),
            items: () => props.libraries,
        },
        hidden: { label: trans('dj.requests_visibility.filter') },
    },
    extra: () => ({ sort: sort.value }),
});

// Rows ticked on this page, for the bulk actions
const selected = ref<Array<string | number>>([]);
const assignOpen = ref(false);

const selectedIds = computed(() => selected.value.map(Number));

function bulkVerify(verified: boolean) {
    router.post(
        verifyArtists.url(),
        { artist_ids: selectedIds.value, verified },
        { preserveScroll: true, onSuccess: () => (selected.value = []) },
    );
}

function bulkHide(hidden: boolean) {
    router.post(
        hideArtists.url(),
        { artist_ids: selectedIds.value, hidden },
        { preserveScroll: true, onSuccess: () => (selected.value = []) },
    );
}

const selectedArtists = computed(() =>
    props.artists.data.filter((artist) =>
        selectedIds.value.includes(artist.id),
    ),
);
</script>

<template>
    <ListLayout
        v-model:search="filters.search"
        v-model:selected="selected"
        :title="trans('dj.artists.title')"
        :columns="columns"
        :rows="artists.data"
        :pagination="artists.meta"
        :search-placeholder="trans('dj.artists.search')"
        :chips="chips"
        :has-filters="hasFilters"
        :selectable="canManageMatching"
        :empty-title="trans('dj.artists.empty')"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template #filters>
            <USelect
                v-model="filters.library_id"
                class="w-full sm:w-44"
                :items="libraries"
                :placeholder="trans('dj.artists.columns.library')"
            />
            <UCheckbox
                v-model="filters.hidden"
                :label="trans('dj.requests_visibility.filter')"
            />
            <USelect
                v-model="sort"
                class="w-full sm:w-40"
                :items="sortItems"
                :aria-label="trans('common.list.sort')"
            />
        </template>

        <template #bulk-actions>
            <UButton
                size="sm"
                icon="i-lucide-link"
                :label="trans('dj.bulk.assign_artist')"
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

        <template #name-cell="{ row }">
            <div class="flex items-center gap-2">
                <Link
                    :href="row.original.tracks_url"
                    class="text-highlighted font-medium hover:underline"
                >
                    {{ row.original.name }}
                </Link>
                <UButton
                    v-if="row.original.canonical_url"
                    as="Link"
                    :href="row.original.canonical_url"
                    size="xs"
                    color="neutral"
                    variant="ghost"
                    icon="i-lucide-user-round"
                    :aria-label="trans('dj.artists.canonical')"
                    :title="trans('dj.artists.canonical')"
                />
                <UBadge
                    v-if="row.original.is_hidden_from_requests"
                    color="neutral"
                    variant="subtle"
                    size="sm"
                    icon="i-lucide-eye-off"
                >
                    {{ trans('dj.requests_visibility.hidden') }}
                </UBadge>
            </div>
        </template>

        <BulkAssignModal
            v-model:open="assignOpen"
            record="artist"
            :ids="selectedIds"
            :default-name="selectedArtists[0]?.name ?? ''"
            @assigned="selected = []"
        />
    </ListLayout>
</template>
