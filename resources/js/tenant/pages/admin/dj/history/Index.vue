<script setup lang="ts">
import { Deferred, Head, Link } from '@inertiajs/vue3';
import {
    createGig,
    link,
    stats,
    unlink,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/HistoryBulkController';
import { index } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/HistoryController';
import { formatDateTime } from '@/lib/timezone';
import type { HistorySessionData, SelectItem } from '@/types';

type SuggestedGig = {
    session_ids: number[];
    names: string[];
    plays_count: number;
    started_at: string | null;
    ended_at: string | null;
};

const props = defineProps<{
    filter: { search?: string; linked?: string; stats?: string };
    sessions: {
        data: HistorySessionData[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
    // Deferred, so they're loaded once the page has shown
    suggestedGigs?: SuggestedGig[];
    suggestedLinks?: Array<{
        session: HistorySessionData;
        gig: { id: number; name: string; has_live_plays: boolean };
    }>;
    gigs: SelectItem[];
    timezone: string;
    canManage: boolean;
}>();

const { trans, transChoice } = useTrans();

const filters = ref({
    search: props.filter.search ?? '',
    linked: props.filter.linked ?? '',
    stats: props.filter.stats ?? '',
});

const search = useDebounceFn((page: number = 1) => {
    const filter = Object.fromEntries(
        Object.entries(filters.value).filter(([, value]) => value !== ''),
    );

    router.get(
        index.url(),
        { page, filter },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}, 300);

watch(filters, () => search(), { deep: true });

const linkedItems = [
    { value: 'linked', label: trans('dj.history.filters.linked') },
    { value: 'unlinked', label: trans('dj.history.filters.unlinked') },
];
const statsItems = [
    { value: 'included', label: trans('dj.history.filters.included') },
    { value: 'excluded', label: trans('dj.history.filters.excluded') },
];

function when(session: {
    started_at: string | null;
    ended_at: string | null;
}): string {
    if (!session.started_at) {
        return '';
    }

    const start = formatDateTime(session.started_at, props.timezone);

    return session.ended_at
        ? `${start} – ${formatDateTime(session.ended_at, props.timezone)}`
        : start;
}

// Only the first few suggestions show, so the sessions aren't pushed out of view
const shownSuggestions = 3;
const showAllLinks = ref(false);
const showAllGigs = ref(false);

// Rows ticked on this page, for the bulk actions
const selected = ref<number[]>([]);

watch(
    () => props.sessions.data,
    () => (selected.value = []),
);

const allSelected = computed(
    () =>
        props.sessions.data.length > 0 &&
        props.sessions.data.every((session) =>
            selected.value.includes(session.id),
        ),
);

function toggleAll(checked: boolean | 'indeterminate') {
    selected.value =
        checked === true
            ? props.sessions.data.map((session) => session.id)
            : [];
}

function toggleRow(id: number, checked: boolean | 'indeterminate') {
    selected.value =
        checked === true
            ? [...new Set([...selected.value, id])]
            : selected.value.filter((selectedId) => selectedId !== id);
}

const columns = computed(() => [
    ...(props.canManage ? [{ accessorKey: 'select', header: '' }] : []),
    { accessorKey: 'name', header: trans('dj.history.columns.session') },
    { accessorKey: 'started_at', header: trans('dj.history.columns.when') },
    { accessorKey: 'gig', header: trans('dj.history.columns.gig') },
    {
        accessorKey: 'include_in_stats',
        header: trans('dj.history.columns.stats'),
    },
]);

const post = (
    url: string,
    data: Parameters<typeof router.post>[1],
    onSuccess?: () => void,
) =>
    router.post(url, data, {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = [];
            onSuccess?.();
        },
    });

// Linking to a gig, or creating one
const linkOpen = ref(false);
const linkGigId = ref<number | undefined>();
const linkIds = ref<number[]>([]);

function openLink(ids: number[]) {
    linkIds.value = ids;
    linkGigId.value = undefined;
    linkOpen.value = true;
}

const createOpen = ref(false);
const createName = ref('');
const createIds = ref<number[]>([]);

function openCreate(ids: number[]) {
    createIds.value = ids;
    createName.value = '';
    createOpen.value = true;
}
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('dj.history.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('dj.history.title')" />

            <div class="flex flex-col gap-6">
                <p class="text-muted text-sm">
                    {{ trans('dj.history.description') }}
                </p>

                <UPageCard
                    v-if="canManage"
                    :title="trans('dj.history.suggestions.title')"
                    variant="subtle"
                >
                    <Deferred :data="['suggestedGigs', 'suggestedLinks']">
                        <template #fallback>
                            <div class="flex flex-col gap-2">
                                <USkeleton class="h-12 w-full" />
                                <USkeleton class="h-12 w-full" />
                            </div>
                        </template>

                        <div class="flex flex-col gap-4">
                            <div
                                v-if="suggestedLinks?.length"
                                class="flex flex-col gap-2"
                            >
                                <h3
                                    class="text-highlighted text-sm font-medium"
                                >
                                    {{ trans('dj.history.suggestions.links') }}
                                </h3>
                                <div
                                    v-for="suggestion in showAllLinks
                                        ? suggestedLinks
                                        : suggestedLinks.slice(
                                              0,
                                              shownSuggestions,
                                          )"
                                    :key="suggestion.session.id"
                                    class="border-default flex flex-wrap items-center justify-between gap-2 rounded-md border p-3"
                                >
                                    <div class="flex flex-col">
                                        <Link
                                            :href="suggestion.session.url"
                                            class="text-highlighted text-sm font-medium hover:underline"
                                        >
                                            {{ suggestion.session.name }}
                                        </Link>
                                        <span class="text-muted text-xs">
                                            {{ when(suggestion.session) }} ·
                                            {{
                                                transChoice(
                                                    'dj.history.plays',
                                                    suggestion.session
                                                        .plays_count,
                                                )
                                            }}
                                        </span>
                                        <span
                                            v-if="suggestion.gig.has_live_plays"
                                            class="text-muted text-xs"
                                        >
                                            {{
                                                trans(
                                                    'dj.history.suggestions.has_live_plays',
                                                )
                                            }}
                                        </span>
                                    </div>
                                    <UButton
                                        size="sm"
                                        icon="i-lucide-link"
                                        :label="
                                            trans(
                                                'dj.history.suggestions.link',
                                                { gig: suggestion.gig.name },
                                            )
                                        "
                                        @click="
                                            post(link.url(), {
                                                session_ids: [
                                                    suggestion.session.id,
                                                ],
                                                gig_id: suggestion.gig.id,
                                            })
                                        "
                                    />
                                </div>
                                <UButton
                                    v-if="
                                        suggestedLinks.length > shownSuggestions
                                    "
                                    size="sm"
                                    variant="link"
                                    class="w-fit px-0"
                                    :label="
                                        showAllLinks
                                            ? trans(
                                                  'dj.history.suggestions.show_less',
                                              )
                                            : trans(
                                                  'dj.history.suggestions.show_all',
                                                  {
                                                      count: suggestedLinks.length,
                                                  },
                                              )
                                    "
                                    @click="showAllLinks = !showAllLinks"
                                />
                            </div>

                            <div
                                v-if="suggestedGigs?.length"
                                class="flex flex-col gap-2"
                            >
                                <div class="flex flex-col">
                                    <h3
                                        class="text-highlighted text-sm font-medium"
                                    >
                                        {{
                                            trans('dj.history.suggestions.gigs')
                                        }}
                                    </h3>
                                    <span class="text-muted text-xs">{{
                                        trans(
                                            'dj.history.suggestions.gigs_help',
                                        )
                                    }}</span>
                                </div>
                                <div
                                    v-for="(group, groupIndex) in showAllGigs
                                        ? suggestedGigs
                                        : suggestedGigs.slice(
                                              0,
                                              shownSuggestions,
                                          )"
                                    :key="groupIndex"
                                    class="border-default flex flex-wrap items-center justify-between gap-2 rounded-md border p-3"
                                >
                                    <div class="flex min-w-0 flex-col">
                                        <span
                                            class="text-highlighted truncate text-sm font-medium"
                                            >{{ group.names.join(', ') }}</span
                                        >
                                        <span class="text-muted text-xs">
                                            {{ when(group) }} ·
                                            {{
                                                transChoice(
                                                    'dj.history.suggestions.sessions',
                                                    group.session_ids.length,
                                                )
                                            }}
                                            ·
                                            {{
                                                transChoice(
                                                    'dj.history.plays',
                                                    group.plays_count,
                                                )
                                            }}
                                        </span>
                                    </div>
                                    <div class="flex gap-2">
                                        <UButton
                                            size="sm"
                                            color="neutral"
                                            variant="outline"
                                            icon="i-lucide-link"
                                            :label="
                                                trans('dj.history.bulk.link')
                                            "
                                            @click="openLink(group.session_ids)"
                                        />
                                        <UButton
                                            size="sm"
                                            icon="i-lucide-calendar-plus"
                                            :label="
                                                trans(
                                                    'dj.history.suggestions.create_gig',
                                                )
                                            "
                                            @click="
                                                openCreate(group.session_ids)
                                            "
                                        />
                                    </div>
                                </div>
                                <UButton
                                    v-if="
                                        suggestedGigs.length > shownSuggestions
                                    "
                                    size="sm"
                                    variant="link"
                                    class="w-fit px-0"
                                    :label="
                                        showAllGigs
                                            ? trans(
                                                  'dj.history.suggestions.show_less',
                                              )
                                            : trans(
                                                  'dj.history.suggestions.show_all',
                                                  {
                                                      count: suggestedGigs.length,
                                                  },
                                              )
                                    "
                                    @click="showAllGigs = !showAllGigs"
                                />
                            </div>

                            <p
                                v-if="
                                    !suggestedGigs?.length &&
                                    !suggestedLinks?.length
                                "
                                class="text-muted text-sm"
                            >
                                {{ trans('dj.history.suggestions.none') }}
                            </p>
                        </div>
                    </Deferred>
                </UPageCard>

                <div class="flex flex-wrap items-center gap-3">
                    <UInput
                        v-model="filters.search"
                        class="w-full sm:w-auto sm:min-w-48 sm:max-w-80 sm:flex-1"
                        icon="i-lucide-search"
                        :placeholder="trans('dj.history.search')"
                    />
                    <USelect
                        v-model="filters.linked"
                        :items="linkedItems"
                        class="w-full sm:w-44"
                        :placeholder="trans('dj.history.columns.gig')"
                    />
                    <USelect
                        v-model="filters.stats"
                        :items="statsItems"
                        class="w-full sm:w-40"
                        :placeholder="trans('dj.history.columns.stats')"
                    />
                </div>

                <div
                    v-if="selected.length"
                    class="bg-elevated flex flex-wrap items-center gap-2 rounded-md px-3 py-2"
                >
                    <span class="text-sm font-medium">{{
                        trans('dj.bulk.selected', { count: selected.length })
                    }}</span>
                    <UButton
                        size="sm"
                        icon="i-lucide-link"
                        :label="trans('dj.history.bulk.link')"
                        @click="openLink(selected)"
                    />
                    <UButton
                        size="sm"
                        color="neutral"
                        variant="outline"
                        icon="i-lucide-calendar-plus"
                        :label="trans('dj.history.bulk.create_gig')"
                        @click="openCreate(selected)"
                    />
                    <UButton
                        size="sm"
                        color="neutral"
                        variant="outline"
                        icon="i-lucide-unlink"
                        :label="trans('dj.history.bulk.unlink')"
                        @click="post(unlink.url(), { session_ids: selected })"
                    />
                    <UButton
                        size="sm"
                        color="neutral"
                        variant="outline"
                        :label="trans('dj.history.bulk.include')"
                        @click="
                            post(stats.url(), {
                                session_ids: selected,
                                include_in_stats: true,
                            })
                        "
                    />
                    <UButton
                        size="sm"
                        color="neutral"
                        variant="outline"
                        :label="trans('dj.history.bulk.exclude')"
                        @click="
                            post(stats.url(), {
                                session_ids: selected,
                                include_in_stats: false,
                            })
                        "
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
                    :columns="columns"
                    :data="sessions.data"
                    :empty="trans('dj.history.empty')"
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
                        <div class="flex flex-col">
                            <Link
                                :href="row.original.url"
                                class="text-highlighted font-medium hover:underline"
                                >{{ row.original.name }}</Link
                            >
                            <span class="text-muted text-xs">
                                {{
                                    transChoice(
                                        'dj.history.plays',
                                        row.original.plays_count,
                                    )
                                }}
                                <template v-if="row.original.library">
                                    · {{ row.original.library }}</template
                                >
                            </span>
                        </div>
                    </template>

                    <template #started_at-cell="{ row }">
                        <span class="whitespace-normal text-sm">{{
                            when(row.original)
                        }}</span>
                    </template>

                    <template #gig-cell="{ row }">
                        <Link
                            v-if="row.original.gig"
                            :href="row.original.gig.url"
                            class="hover:underline"
                            >{{ row.original.gig.name }}</Link
                        >
                        <span v-else class="text-muted">{{
                            trans('dj.history.not_linked')
                        }}</span>
                    </template>

                    <template #include_in_stats-cell="{ row }">
                        <UBadge
                            :color="
                                row.original.include_in_stats
                                    ? 'success'
                                    : 'neutral'
                            "
                            variant="subtle"
                        >
                            {{
                                row.original.include_in_stats
                                    ? trans('dj.history.in_stats')
                                    : trans('dj.history.not_in_stats')
                            }}
                        </UBadge>
                    </template>
                </UTable>

                <div
                    v-if="sessions.meta.last_page > 1"
                    class="flex justify-end"
                >
                    <UPagination
                        :items-per-page="sessions.meta.per_page"
                        :page="sessions.meta.current_page"
                        :total="sessions.meta.total"
                        @update:page="(page: number) => search(page)"
                    />
                </div>
            </div>

            <UModal
                v-model:open="linkOpen"
                :title="trans('dj.history.bulk.link_title')"
            >
                <template #body>
                    <UFormField
                        :label="trans('dj.history.fields.gig')"
                        required
                    >
                        <USelectMenu
                            v-model="linkGigId"
                            :items="gigs"
                            value-key="value"
                            class="w-full"
                        />
                    </UFormField>
                </template>
                <template #footer>
                    <div class="flex w-full justify-end gap-2">
                        <UButton
                            color="neutral"
                            variant="ghost"
                            :label="trans('common.actions.cancel')"
                            @click="linkOpen = false"
                        />
                        <UButton
                            :disabled="!linkGigId"
                            :label="trans('dj.history.bulk.link')"
                            @click="
                                post(
                                    link.url(),
                                    { session_ids: linkIds, gig_id: linkGigId },
                                    () => (linkOpen = false),
                                )
                            "
                        />
                    </div>
                </template>
            </UModal>

            <UModal
                v-model:open="createOpen"
                :title="trans('dj.history.bulk.create_gig_title')"
                :description="trans('dj.history.bulk.create_gig_help')"
            >
                <template #body>
                    <UFormField :label="trans('dj.history.fields.gig_name')">
                        <UInput
                            v-model="createName"
                            class="w-full"
                            :placeholder="
                                trans('dj.history.gig_name', { date: '…' })
                            "
                            autofocus
                        />
                    </UFormField>
                </template>
                <template #footer>
                    <div class="flex w-full justify-end gap-2">
                        <UButton
                            color="neutral"
                            variant="ghost"
                            :label="trans('common.actions.cancel')"
                            @click="createOpen = false"
                        />
                        <UButton
                            :label="trans('dj.history.suggestions.create_gig')"
                            @click="
                                router.post(createGig.url(), {
                                    session_ids: createIds,
                                    name: createName || null,
                                })
                            "
                        />
                    </div>
                </template>
            </UModal>
        </template>
    </UDashboardPanel>
</template>
