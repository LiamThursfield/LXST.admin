<script setup lang="ts" generic="T extends Record<string, any>">
import { Head } from '@inertiajs/vue3';
import type { DropdownMenuItem } from '@nuxt/ui';
// Imported explicitly: the central app doesn't use these yet, so its generated component types lack them.
import UEmpty from '@nuxt/ui/components/Empty.vue';
import UFieldGroup from '@nuxt/ui/components/FieldGroup.vue';
import { breakpointsTailwind, useBreakpoints } from '@vueuse/core';
import type { ListFilterChip } from '@/composables/useListFilters';

/** A list's page of results, as Laravel's paginated resources give it. */
export type ListPagination = {
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
};

/**
 * The page around an admin list: a navbar with the title, a count and the create action; a toolbar
 * with search, the page's filters, the sort and a Columns menu; chips for the active filters; a
 * floating bar of bulk actions while rows are ticked; the table, which scrolls sideways in its own box; a footer saying
 * which rows are showing, with previous and next; and empty states for "nothing yet" and "no
 * matches". Each column's cells can be customised with the table's `{column}-cell` slots, and the
 * default slot is for anything else on the page, e.g. a modal.
 *
 * The toolbar fits the width: on wide screens one row, with search and filters on the left (they
 * wrap among themselves) and the sort and Columns fixed on the right; at mid widths search and
 * those view controls on top, with the filters on a row below; on phones search, a Filters button
 * that opens a sheet holding the filters and sort, and Columns. Rarely used filters can go in
 * `more-filters`, behind a More filters button (always in the sheet on phones).
 */
const props = withDefaults(
    defineProps<{
        title: string;
        /** The columns, as `{ accessorKey, header }`; `actions` (row actions) goes last. */
        columns: Array<{ accessorKey: string; header: string }>;
        rows: T[];
        /** Each row's unique key, for ticking rows. */
        rowKey?: keyof T & string;
        pagination?: ListPagination | null;
        /** Shows the search box, with this placeholder. */
        searchPlaceholder?: string;
        chips?: ListFilterChip[];
        /** Whether any filter (or search) is on, for the "no matches" empty state. */
        hasFilters?: boolean;
        /** Rows can be ticked, for the bulk actions. */
        selectable?: boolean;
        /**
         * The column whose cells link to the record, from each row's actions (its edit page when the
         * user can edit it). A page with its own cells for this column links them itself.
         */
        linkColumn?: string;
        /** The link column goes to the record page rather than its edit page. */
        linkToRecordPage?: boolean;
        emptyTitle: string;
        emptyDescription?: string;
        /** The create action, in the navbar and the "nothing yet" empty state. */
        createLabel?: string;
        createHref?: string;
        /** The fields the list can be sorted by; `descending` ones start from the highest. */
        sortOptions?: Array<{
            value: string;
            label: string;
            descending?: boolean;
        }>;
    }>(),
    {
        rowKey: 'id' as never,
        pagination: null,
        searchPlaceholder: undefined,
        chips: () => [],
        hasFilters: false,
        selectable: false,
        linkColumn: 'name',
        linkToRecordPage: false,
        emptyDescription: undefined,
        createLabel: undefined,
        createHref: undefined,
        sortOptions: () => [],
    },
);

const emit = defineEmits<{
    removeFilter: [key: string];
    clearFilters: [];
    page: [page: number];
}>();

const search = defineModel<string>('search', { default: '' });

/** The sort, e.g. `title`, or `-title` for descending. */
const sort = defineModel<string>('sort', { default: '' });

/** Whether the More filters are showing, on wide screens. */
const moreOpen = defineModel<boolean>('moreOpen', { default: false });

/** The ticked rows' keys. */
const selected = defineModel<Array<string | number>>('selected', {
    default: () => [],
});

const slots = useSlots();

const { trans, transChoice } = useTrans();

/** The slots this layout uses itself; the rest go to the table. */
const ownSlots = [
    'default',
    'actions',
    'filters',
    'more-filters',
    'bulk-actions',
    'notice',
];

const tableSlots = computed(() =>
    Object.keys(slots).filter((name) => !ownSlots.includes(name)),
);

const linkSlot = computed(() => `${props.linkColumn}-cell`);

/** The link column's cells, unless the page renders them itself. */
const linksRecords = computed(() => !slots[linkSlot.value]);

// A new page of rows starts with none ticked
watch(
    () => props.rows,
    () => {
        selected.value = [];
    },
);

function keyOf(row: T): string | number {
    return row[props.rowKey as keyof T];
}

const allSelected = computed(
    () =>
        props.rows.length > 0 &&
        props.rows.every((row) => selected.value.includes(keyOf(row))),
);

function toggleAll(checked: boolean | 'indeterminate') {
    selected.value = checked === true ? props.rows.map(keyOf) : [];
}

function toggleRow(row: T, checked: boolean | 'indeterminate') {
    const key = keyOf(row);

    selected.value =
        checked === true
            ? [...new Set([...selected.value, key])]
            : selected.value.filter((selectedKey) => selectedKey !== key);
}

/** Columns hidden from the Columns menu, by accessor key. */
const columnVisibility = ref<Record<string, boolean>>({});

/** The columns the Columns menu can hide: not the ticks or the row actions. */
const hideableColumns = computed(() =>
    props.columns.filter(
        (column) =>
            !['select', 'actions'].includes(column.accessorKey) &&
            column.header,
    ),
);

const columnItems = computed<DropdownMenuItem[]>(() =>
    hideableColumns.value.map((column) => ({
        label: column.header,
        type: 'checkbox' as const,
        checked: columnVisibility.value[column.accessorKey] !== false,
        onUpdateChecked: (checked: boolean) => {
            columnVisibility.value = {
                ...columnVisibility.value,
                [column.accessorKey]: checked,
            };
        },
        onSelect: (event: Event) => event.preventDefault(),
    })),
);

const tableColumns = computed(() =>
    props.selectable
        ? [{ accessorKey: 'select', header: '' }, ...props.columns]
        : props.columns,
);

const showing = computed(() => {
    const page = props.pagination;

    if (!page || !page.total) {
        return null;
    }

    const from = (page.current_page - 1) * page.per_page + 1;

    return trans('common.list.showing', {
        from,
        to: Math.min(page.current_page * page.per_page, page.total),
        total: page.total,
    });
});

const total = computed(() => props.pagination?.total ?? props.rows.length);

const breakpoints = useBreakpoints(breakpointsTailwind);

/** One toolbar row, with the filters inline (the sidebar takes room below this). */
const isWide = breakpoints.greaterOrEqual('xl');

/** The filters and sort in a sheet, behind a Filters button. */
const isPhone = breakpoints.smaller('md');

const hasFilterControls = computed(
    () => !!slots.filters || !!slots['more-filters'],
);

const hasToolbar = computed(
    () =>
        !!props.searchPlaceholder ||
        hasFilterControls.value ||
        props.sortOptions.length > 0 ||
        hideableColumns.value.length > 1,
);

const sheetOpen = ref(false);

const sortField = computed(() => sort.value.replace(/^-/, ''));
const sortDescending = computed(() => sort.value.startsWith('-'));

function chooseSort(field: string) {
    const option = props.sortOptions.find((item) => item.value === field);

    sort.value = option?.descending ? `-${field}` : field;
}

function toggleSortDirection() {
    sort.value = sortDescending.value ? sortField.value : `-${sortField.value}`;
}
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar>
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>

                <template #title>
                    <div class="flex min-w-0 items-center gap-2">
                        <h1 class="text-highlighted truncate font-semibold">
                            {{ title }}
                        </h1>
                        <UBadge
                            color="neutral"
                            variant="subtle"
                            class="shrink-0"
                            :label="String(total)"
                        />
                    </div>
                </template>

                <template #right>
                    <slot name="actions" />
                    <UButton
                        v-if="createHref && createLabel"
                        as="Link"
                        :href="createHref"
                        icon="i-lucide-plus"
                        :label="createLabel"
                        :aria-label="createLabel"
                        :ui="{ label: 'hidden sm:inline' }"
                    />
                </template>
            </UDashboardNavbar>

            <UDashboardToolbar
                v-if="hasToolbar"
                :ui="{ root: 'min-h-0 overflow-visible py-2' }"
            >
                <div class="flex w-full flex-col gap-2">
                    <div class="flex items-start gap-2">
                        <div
                            class="flex min-w-0 flex-1 items-start gap-2 xl:flex-wrap"
                        >
                            <UInput
                                v-if="searchPlaceholder"
                                v-model="search"
                                icon="i-lucide-search"
                                :placeholder="searchPlaceholder"
                                :aria-label="searchPlaceholder"
                                class="min-w-0 flex-1 xl:w-72 xl:flex-none"
                            />

                            <template v-if="isWide">
                                <slot name="filters" />
                                <UButton
                                    v-if="$slots['more-filters']"
                                    color="neutral"
                                    variant="ghost"
                                    icon="i-lucide-sliders-horizontal"
                                    :label="trans('common.list.more_filters')"
                                    :aria-expanded="moreOpen"
                                    @click="moreOpen = !moreOpen"
                                />
                            </template>

                            <UButton
                                v-if="
                                    isPhone &&
                                    (hasFilterControls || sortOptions.length)
                                "
                                color="neutral"
                                variant="outline"
                                icon="i-lucide-sliders-horizontal"
                                class="shrink-0"
                                :label="trans('common.list.filters')"
                                @click="sheetOpen = true"
                            >
                                <template v-if="chips.length" #trailing>
                                    <UBadge
                                        color="neutral"
                                        size="sm"
                                        class="rounded-full"
                                        :label="String(chips.length)"
                                    />
                                </template>
                            </UButton>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <UFieldGroup v-if="sortOptions.length && !isPhone">
                                <USelect
                                    :model-value="sortField"
                                    :items="sortOptions"
                                    :aria-label="trans('common.list.sort')"
                                    :ui="{ base: 'ps-12' }"
                                    class="w-48"
                                    @update:model-value="
                                        (field) => chooseSort(String(field))
                                    "
                                >
                                    <template #leading>
                                        <span class="text-muted text-sm">
                                            {{ trans('common.list.sort_by') }}
                                        </span>
                                    </template>
                                </USelect>
                                <UButton
                                    color="neutral"
                                    variant="outline"
                                    :icon="
                                        sortDescending
                                            ? 'i-lucide-arrow-down-wide-narrow'
                                            : 'i-lucide-arrow-up-narrow-wide'
                                    "
                                    :aria-label="
                                        sortDescending
                                            ? trans(
                                                  'common.list.sort_descending',
                                              )
                                            : trans(
                                                  'common.list.sort_ascending',
                                              )
                                    "
                                    @click="toggleSortDirection"
                                />
                            </UFieldGroup>

                            <UDropdownMenu
                                v-if="hideableColumns.length > 1"
                                :items="columnItems"
                                :content="{ align: 'end' }"
                            >
                                <UButton
                                    color="neutral"
                                    variant="outline"
                                    icon="i-lucide-columns-3"
                                    :label="trans('common.list.columns')"
                                    :aria-label="trans('common.list.columns')"
                                    :ui="{ label: 'hidden lg:inline' }"
                                />
                            </UDropdownMenu>
                        </div>
                    </div>

                    <div
                        v-if="!isWide && !isPhone && hasFilterControls"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <slot name="filters" />
                        <UButton
                            v-if="$slots['more-filters']"
                            color="neutral"
                            variant="ghost"
                            icon="i-lucide-sliders-horizontal"
                            :label="trans('common.list.more_filters')"
                            :aria-expanded="moreOpen"
                            @click="moreOpen = !moreOpen"
                        />
                    </div>
                </div>
            </UDashboardToolbar>
        </template>

        <template #body>
            <Head :title="title" />

            <div class="flex flex-col gap-4">
                <slot name="notice" />

                <div v-if="moreOpen && !isPhone && $slots['more-filters']">
                    <slot name="more-filters" />
                </div>

                <!-- One row that scrolls sideways on phones -->
                <div
                    v-if="chips.length"
                    class="-mx-1 flex items-center gap-2 overflow-x-auto px-1 md:flex-wrap"
                >
                    <UButton
                        v-for="chip in chips"
                        :key="chip.key"
                        color="neutral"
                        variant="soft"
                        size="xs"
                        class="shrink-0 rounded-full"
                        trailing-icon="i-lucide-x"
                        :label="chip.label"
                        :aria-label="
                            trans('common.list.remove_filter', {
                                filter: chip.label,
                            })
                        "
                        @click="emit('removeFilter', chip.key)"
                    />
                    <UButton
                        color="neutral"
                        variant="link"
                        size="xs"
                        :label="trans('common.list.clear_filters')"
                        @click="emit('clearFilters')"
                    />
                </div>

                <UEmpty
                    v-if="!rows.length && hasFilters"
                    icon="i-lucide-search-x"
                    :title="trans('common.list.no_matches')"
                    :description="trans('common.list.no_matches_description')"
                    variant="naked"
                >
                    <template #actions>
                        <UButton
                            color="neutral"
                            variant="outline"
                            :label="trans('common.list.clear_filters')"
                            @click="emit('clearFilters')"
                        />
                    </template>
                </UEmpty>

                <UEmpty
                    v-else-if="!rows.length"
                    icon="i-lucide-inbox"
                    :title="emptyTitle"
                    :description="emptyDescription"
                    variant="naked"
                >
                    <template v-if="createHref && createLabel" #actions>
                        <UButton
                            as="Link"
                            :href="createHref"
                            icon="i-lucide-plus"
                            :label="createLabel"
                        />
                    </template>
                </UEmpty>

                <div
                    v-else
                    class="ring-default overflow-hidden rounded-lg ring"
                >
                    <UTable
                        v-model:column-visibility="columnVisibility"
                        :columns="tableColumns"
                        :data="rows"
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
                                :aria-label="trans('common.list.select_all')"
                                @update:model-value="toggleAll"
                            />
                        </template>

                        <template #select-cell="{ row }">
                            <UCheckbox
                                :model-value="
                                    selected.includes(keyOf(row.original))
                                "
                                :aria-label="trans('common.list.select_row')"
                                @update:model-value="
                                    (checked) =>
                                        toggleRow(row.original, checked)
                                "
                            />
                        </template>

                        <template v-if="linksRecords" #[linkSlot]="{ row }">
                            <ListRecordLink
                                :actions="row.original.actions"
                                :to-record-page="linkToRecordPage"
                            >
                                {{ row.original[linkColumn] }}
                            </ListRecordLink>
                        </template>

                        <template
                            v-for="name in tableSlots"
                            #[name]="slotProps"
                            :key="name"
                        >
                            <slot :name="name" v-bind="slotProps ?? {}" />
                        </template>
                    </UTable>
                </div>

                <!-- Below the table, floating, so ticking rows never moves the rows -->
                <div
                    v-if="selectable && selected.length"
                    class="bg-default ring-default sticky bottom-4 z-10 flex flex-wrap items-center gap-2 rounded-lg px-3 py-2 shadow-lg ring"
                >
                    <span class="text-sm font-medium">
                        {{
                            transChoice('common.list.selected', selected.length)
                        }}
                    </span>
                    <slot name="bulk-actions" />
                    <UButton
                        color="neutral"
                        variant="ghost"
                        size="sm"
                        icon="i-lucide-x"
                        class="ms-auto"
                        :aria-label="trans('common.list.clear_selection')"
                        @click="selected = []"
                    />
                </div>

                <div
                    v-if="showing && rows.length"
                    class="flex flex-wrap items-center justify-between gap-3"
                >
                    <p class="text-muted text-sm">{{ showing }}</p>
                    <div
                        v-if="pagination && pagination.last_page > 1"
                        class="flex gap-2"
                    >
                        <UButton
                            color="neutral"
                            variant="outline"
                            size="sm"
                            icon="i-lucide-chevron-left"
                            :label="trans('common.list.previous')"
                            :disabled="pagination.current_page <= 1"
                            @click="emit('page', pagination.current_page - 1)"
                        />
                        <UButton
                            color="neutral"
                            variant="outline"
                            size="sm"
                            trailing-icon="i-lucide-chevron-right"
                            :label="trans('common.list.next')"
                            :disabled="
                                pagination.current_page >= pagination.last_page
                            "
                            @click="emit('page', pagination.current_page + 1)"
                        />
                    </div>
                </div>
            </div>

            <USlideover
                v-if="isPhone"
                v-model:open="sheetOpen"
                side="bottom"
                :title="trans('common.list.filters')"
            >
                <template #body>
                    <div class="flex flex-col gap-4 *:w-full">
                        <slot name="filters" />
                        <slot name="more-filters" />

                        <UFormField
                            v-if="sortOptions.length"
                            :label="trans('common.list.sort_by')"
                        >
                            <UFieldGroup class="w-full">
                                <USelect
                                    :model-value="sortField"
                                    :items="sortOptions"
                                    :aria-label="trans('common.list.sort')"
                                    class="flex-1"
                                    @update:model-value="
                                        (field) => chooseSort(String(field))
                                    "
                                />
                                <UButton
                                    color="neutral"
                                    variant="outline"
                                    :icon="
                                        sortDescending
                                            ? 'i-lucide-arrow-down-wide-narrow'
                                            : 'i-lucide-arrow-up-narrow-wide'
                                    "
                                    :aria-label="
                                        sortDescending
                                            ? trans(
                                                  'common.list.sort_descending',
                                              )
                                            : trans(
                                                  'common.list.sort_ascending',
                                              )
                                    "
                                    @click="toggleSortDirection"
                                />
                            </UFieldGroup>
                        </UFormField>
                    </div>
                </template>

                <template #footer>
                    <div class="flex w-full gap-2">
                        <UButton
                            color="neutral"
                            variant="outline"
                            size="lg"
                            :label="trans('common.list.clear_filters')"
                            @click="emit('clearFilters')"
                        />
                        <UButton
                            size="lg"
                            class="flex-1 justify-center"
                            :label="trans('common.list.show_results')"
                            @click="sheetOpen = false"
                        />
                    </div>
                </template>
            </USlideover>

            <slot />
        </template>
    </UDashboardPanel>
</template>
