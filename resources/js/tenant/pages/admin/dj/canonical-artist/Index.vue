<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { index } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/CanonicalArtistController';
import type { CanonicalArtistData } from '@/types';

const props = defineProps<{
    filter: {
        search?: string;
        verified?: string;
        orphaned?: boolean | string;
        hidden?: boolean | string;
    };
    sort: string;
    columns: Array<{ accessorKey: string; header: string }>;
    artists: {
        data: CanonicalArtistData[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
}>();

const { trans } = useTrans();

const sortOptions = [
    { value: 'name', label: trans('dj.canonical_artists.sorts.name') },
    {
        value: 'tracks_count',
        label: trans('dj.canonical_artists.sorts.tracks_count'),
        descending: true,
    },
    {
        value: 'library_artists_count',
        label: trans('dj.canonical_artists.sorts.library_artists_count'),
        descending: true,
    },
];
const sort = ref(props.sort);

const verifiedItems = [
    { value: 'yes', label: trans('dj.songs.filters.verified_yes') },
    { value: 'no', label: trans('dj.songs.filters.verified_no') },
];

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => index.url(),
    initial: {
        search: props.filter.search ?? '',
        verified: props.filter.verified ?? '',
        orphaned:
            props.filter.orphaned === true || props.filter.orphaned === '1',
        hidden: props.filter.hidden === true || props.filter.hidden === '1',
    },
    labels: {
        verified: {
            label: trans('dj.songs.filters.verified'),
            items: verifiedItems,
        },
        orphaned: { label: trans('dj.canonical_artists.filters.orphaned') },
        hidden: { label: trans('dj.requests_visibility.filter') },
    },
    extra: () => ({ sort: sort.value }),
});
</script>

<template>
    <ListLayout
        v-model:search="filters.search"
        :title="trans('dj.canonical_artists.title')"
        :columns="columns"
        :rows="artists.data"
        :pagination="artists.meta"
        :search-placeholder="trans('dj.canonical_artists.search')"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('dj.canonical_artists.empty')"
        v-model:sort="sort"
        :sort-options="sortOptions"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template #notice>
            <p class="text-muted text-sm">
                {{ trans('dj.canonical_artists.description') }}
            </p>
        </template>

        <template #filters>
            <USelect
                v-model="filters.verified"
                class="w-full sm:w-40"
                :items="verifiedItems"
                :placeholder="trans('dj.songs.filters.verified')"
            />
            <ListFilterToggle
                v-model="filters.orphaned"
                :label="trans('dj.canonical_artists.filters.orphaned')"
            />
            <ListFilterToggle
                v-model="filters.hidden"
                :label="trans('dj.requests_visibility.filter')"
            />
        </template>

        <template #name-cell="{ row }">
            <span class="flex items-center gap-1">
                <Link
                    :href="row.original.url"
                    class="text-highlighted font-medium hover:underline"
                >
                    {{ row.original.name }}
                </Link>
                <UIcon
                    v-if="row.original.is_verified"
                    name="i-lucide-badge-check"
                    class="text-success size-4"
                    :aria-label="trans('dj.matching.verified')"
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
            </span>
        </template>
    </ListLayout>
</template>
