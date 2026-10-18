<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { verify as verifyArtists } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/LibraryArtistBulkController';
import { index } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/LibraryArtistController';
import BulkAssignModal from '@/tenant/components/dj/BulkAssignModal.vue';
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
    canManageMatching: boolean;
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

// Rows ticked on this page, for the bulk actions
const selected = ref<number[]>([]);
const assignOpen = ref(false);

watch(
    () => props.artists.data,
    () => (selected.value = []),
);

const allSelected = computed(
    () =>
        props.artists.data.length > 0 &&
        props.artists.data.every((row) => selected.value.includes(row.id)),
);

function toggleAll(checked: boolean | 'indeterminate') {
    selected.value =
        checked === true ? props.artists.data.map((row) => row.id) : [];
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
        verifyArtists.url(),
        { artist_ids: selected.value, verified },
        { preserveScroll: true, onSuccess: () => (selected.value = []) },
    );
}

const selectedArtists = computed(() =>
    props.artists.data.filter((artist) => selected.value.includes(artist.id)),
);
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
                        variant="ghost"
                        class="ms-auto"
                        :label="trans('dj.bulk.clear')"
                        @click="selected = []"
                    />
                </div>

                <UTable
                    :columns="tableColumns"
                    :data="artists.data"
                    :empty="trans('dj.artists.empty')"
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
                                :as="Link"
                                :href="row.original.canonical_url"
                                size="xs"
                                color="neutral"
                                variant="ghost"
                                icon="i-lucide-user-round"
                                :aria-label="trans('dj.artists.canonical')"
                                :title="trans('dj.artists.canonical')"
                            />
                        </div>
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

            <BulkAssignModal
                v-model:open="assignOpen"
                record="artist"
                :ids="selected"
                :default-name="selectedArtists[0]?.name ?? ''"
                @assigned="selected = []"
            />
        </template>
    </UDashboardPanel>
</template>
