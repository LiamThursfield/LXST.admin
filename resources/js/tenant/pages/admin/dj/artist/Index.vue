<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { index } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/LibraryArtistController';
import type { LibraryArtistData, SelectItem } from '@/types';

const props = defineProps<{
    filter: { search?: string; library_id?: string };
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
}>();

const { trans } = useTrans();

const filters = ref({
    search: props.filter.search ?? '',
    library_id: props.filter.library_id ? String(props.filter.library_id) : '',
});

const sortItems = [
    { value: 'name', label: trans('dj.artists.columns.name') },
    { value: '-tracks_count', label: trans('dj.artists.columns.tracks') },
];
const sort = ref(props.sort);

const search = useDebounceFn((page: number = 1) => {
    const filter = Object.fromEntries(
        Object.entries(filters.value).filter(([, value]) => value !== ''),
    );

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
            <UDashboardNavbar :title="trans('dj.artists.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('dj.artists.title')" />

            <div class="flex flex-col gap-6">
                <div class="flex flex-wrap items-center gap-3">
                    <UInput
                        v-model="filters.search"
                        class="w-full sm:max-w-72"
                        icon="i-lucide-search"
                        :placeholder="trans('dj.artists.search')"
                    />
                    <USelect
                        v-model="filters.library_id"
                        class="w-full sm:w-44"
                        :items="libraries"
                        :placeholder="trans('dj.artists.columns.library')"
                    />
                    <USelect
                        v-model="sort"
                        class="w-full sm:ms-auto sm:w-40"
                        :items="sortItems"
                    />
                </div>

                <UTable
                    :columns="columns"
                    :data="artists.data"
                    :empty="trans('dj.artists.empty')"
                >
                    <template #name-cell="{ row }">
                        <Link
                            :href="row.original.tracks_url"
                            class="text-highlighted font-medium hover:underline"
                        >
                            {{ row.original.name }}
                        </Link>
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
