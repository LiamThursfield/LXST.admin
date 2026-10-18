<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
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

const filters = ref({
    search: props.filter.search ?? '',
    artist_id: props.filter.artist_id ? String(props.filter.artist_id) : '',
    verified: props.filter.verified ?? '',
    key: props.filter.key ?? '',
    shared: isTrue(props.filter.shared),
    orphaned: isTrue(props.filter.orphaned),
});

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
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('dj.songs.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>

                <template #right>
                    <span class="text-muted text-sm">
                        {{
                            trans('dj.songs.total', { count: songs.meta.total })
                        }}
                    </span>
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('dj.songs.title')" />

            <div class="flex flex-col gap-6">
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

                <div class="flex flex-col gap-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <UInput
                            v-model="filters.search"
                            class="w-full sm:w-auto sm:min-w-48 sm:max-w-80 sm:flex-1"
                            icon="i-lucide-search"
                            :placeholder="trans('dj.songs.search')"
                        />
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
                    </div>

                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                        <UCheckbox
                            v-model="filters.shared"
                            :label="trans('dj.songs.filters.shared')"
                        />
                        <UCheckbox
                            v-model="filters.orphaned"
                            :label="trans('dj.songs.filters.orphaned')"
                        />
                        <UBadge
                            v-if="filters.artist_id && artist"
                            color="primary"
                            variant="subtle"
                            class="gap-1"
                        >
                            {{
                                trans('dj.songs.filters.artist', {
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
                </div>

                <UTable
                    :columns="columns"
                    :data="songs.data"
                    :empty="trans('dj.songs.empty')"
                >
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
                            </span>
                        </div>
                    </template>

                    <template #bpm-cell="{ row }">{{
                        row.original.bpm ?? ''
                    }}</template>

                    <template #key-cell="{ row }">
                        <UBadge
                            v-if="row.original.key"
                            color="neutral"
                            variant="subtle"
                        >
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
                </UTable>

                <div v-if="songs.meta.last_page > 1" class="flex justify-end">
                    <UPagination
                        :items-per-page="songs.meta.per_page"
                        :page="songs.meta.current_page"
                        :total="songs.meta.total"
                        @update:page="(page: number) => search(page)"
                    />
                </div>
            </div>

            <UModal
                v-model:open="cleanupOpen"
                :title="trans('dj.songs.cleanup')"
            >
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
        </template>
    </UDashboardPanel>
</template>
