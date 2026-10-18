<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
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

const filters = ref({
    search: props.filter.search ?? '',
    verified: props.filter.verified ?? '',
    orphaned: props.filter.orphaned === true || props.filter.orphaned === '1',
    hidden: props.filter.hidden === true || props.filter.hidden === '1',
});

const sortItems = [
    { value: 'name', label: trans('dj.canonical_artists.sorts.name') },
    {
        value: '-tracks_count',
        label: trans('dj.canonical_artists.sorts.tracks_count'),
    },
    {
        value: '-library_artists_count',
        label: trans('dj.canonical_artists.sorts.library_artists_count'),
    },
];
const sort = ref(props.sort);

const verifiedItems = [
    { value: 'yes', label: trans('dj.songs.filters.verified_yes') },
    { value: 'no', label: trans('dj.songs.filters.verified_no') },
];

const search = useDebounceFn((page: number = 1) => {
    const filter: Record<string, string> = {};

    if (filters.value.search) {
        filter.search = filters.value.search;
    }

    if (filters.value.verified) {
        filter.verified = filters.value.verified;
    }

    if (filters.value.orphaned) {
        filter.orphaned = '1';
    }

    if (filters.value.hidden) {
        filter.hidden = '1';
    }

    router.get(
        index.url(),
        { page, filter, sort: sort.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}, 300);

watch([filters, sort], () => search(), { deep: true });
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('dj.canonical_artists.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('dj.canonical_artists.title')" />

            <div class="flex flex-col gap-6">
                <p class="text-muted text-sm">
                    {{ trans('dj.canonical_artists.description') }}
                </p>

                <div class="flex flex-wrap items-center gap-3">
                    <UInput
                        v-model="filters.search"
                        class="w-full sm:w-auto sm:min-w-48 sm:max-w-80 sm:flex-1"
                        icon="i-lucide-search"
                        :placeholder="trans('dj.canonical_artists.search')"
                    />
                    <USelect
                        v-model="filters.verified"
                        class="w-full sm:w-40"
                        :items="verifiedItems"
                        :placeholder="trans('dj.songs.filters.verified')"
                    />
                    <UCheckbox
                        v-model="filters.orphaned"
                        :label="trans('dj.canonical_artists.filters.orphaned')"
                    />
                    <UCheckbox
                        v-model="filters.hidden"
                        :label="trans('dj.requests_visibility.filter')"
                    />
                    <USelect
                        v-model="sort"
                        class="w-full sm:ms-auto sm:w-44"
                        :items="sortItems"
                        :aria-label="trans('dj.tracks.filters.sort')"
                    />
                </div>

                <UTable
                    :columns="columns"
                    :data="artists.data"
                    :empty="trans('dj.canonical_artists.empty')"
                >
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
                </UTable>

                <div v-if="artists.meta.last_page > 1" class="flex justify-end">
                    <UPagination
                        :items-per-page="artists.meta.per_page"
                        :page="artists.meta.current_page"
                        :total="artists.meta.total"
                        @update:page="(page: number) => search(page)"
                    />
                </div>
            </div>
        </template>
    </UDashboardPanel>
</template>
