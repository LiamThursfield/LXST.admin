<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    index,
    show,
} from '@/actions/App/Http/Controllers/Central/Admin/TenantController';
import RowActions from '@/components/RowActions.vue';
import { formatDateTime, userTimezone } from '@/lib/timezone';
import type { SelectItem, TenantData } from '@/types';

type SortKey = 'name' | 'id' | 'created_at';

const props = defineProps<{
    filter?: {
        search?: string;
        module?: string;
    };
    sort?: string | null;
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    tenants: {
        data: TenantData[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
    modules: SelectItem[];
}>();

const { trans } = useTrans();
const timezone = userTimezone(useAuth().value.user);

const searchFilter = ref({
    search: props.filter?.search || '',
    module: props.filter?.module || '',
});

const sort = ref<string>(props.sort || 'name');

/** The columns that can be sorted, so their headers become sort buttons */
const sortableColumns: SortKey[] = ['name', 'id', 'created_at'];

function headerFor(key: SortKey): string {
    return (
        props.columns.find((column) => column.accessorKey === key)?.header ?? ''
    );
}

function sortIcon(key: SortKey): string {
    if (sort.value === key) {
        return 'i-lucide-arrow-up-narrow-wide';
    }

    if (sort.value === `-${key}`) {
        return 'i-lucide-arrow-down-wide-narrow';
    }

    return 'i-lucide-arrow-up-down';
}

function toggleSort(key: SortKey): void {
    sort.value = sort.value === key ? `-${key}` : key;
}

const search = useDebounceFn((page: number = 1) => {
    const query = new URLSearchParams();
    const filter: Record<string, string> = {};

    const newValue = searchFilter.value;

    if (newValue.search.length) {
        query.append('filter[search]', newValue.search);
        filter.search = newValue.search;
    }

    if (newValue.module.length) {
        query.append('filter[module]', newValue.module);
        filter.module = newValue.module;
    }

    if (sort.value !== 'name') {
        query.append('sort', sort.value);
    }

    let url = `${window.location.pathname}`;
    if (query.size > 0) {
        url += `?${query.toString()}`;
    }
    window.history.pushState({}, '', url);

    router.get(
        index.url(),
        {
            page,
            filter,
            ...(sort.value !== 'name' ? { sort: sort.value } : {}),
        },
        { preserveState: true, replace: true },
    );
}, 300);

watch([searchFilter, sort], () => search(), { deep: true });
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('tenants.index.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('tenants.index.title')" />
            <div class="flex flex-col gap-8">
                <div
                    class="flex flex-wrap items-center justify-between gap-8 px-2 py-4"
                >
                    <div class="flex flex-1 items-center gap-4">
                        <UInput
                            class="max-w-72"
                            icon="i-lucide-search"
                            :placeholder="trans('tenants.filters.search')"
                            v-model="searchFilter.search"
                        />
                        <USelect
                            class="w-full max-w-40"
                            :items="modules"
                            :placeholder="trans('tenants.filters.module')"
                            v-model="searchFilter.module"
                        />
                    </div>
                </div>

                <UTable
                    :columns="columns"
                    :data="tenants.data"
                    :empty="trans('tenants.index.empty')"
                >
                    <template
                        v-for="key in sortableColumns"
                        :key="key"
                        #[`${key}-header`]
                    >
                        <UButton
                            color="neutral"
                            variant="ghost"
                            class="-mx-2.5"
                            :label="headerFor(key)"
                            :trailing-icon="sortIcon(key)"
                            @click="toggleSort(key)"
                        />
                    </template>

                    <template #name-cell="{ row }">
                        <Link
                            :href="show.url(row.original.id)"
                            class="text-highlighted font-medium hover:underline"
                        >
                            {{ row.original.name }}
                        </Link>
                    </template>

                    <template #id-cell="{ row }">
                        <span class="text-muted font-mono text-xs">{{
                            row.original.id
                        }}</span>
                    </template>

                    <template #domains-cell="{ row }">
                        <div class="flex flex-col gap-0.5">
                            <span
                                v-for="domain in row.original.domains"
                                :key="domain.domain"
                                class="font-mono text-xs"
                            >
                                {{ domain.domain }}
                            </span>
                        </div>
                    </template>

                    <template #modules-cell="{ row }">
                        <div class="flex flex-wrap gap-1">
                            <UBadge
                                v-for="module in row.original.modules"
                                :key="module.value"
                                color="neutral"
                                variant="subtle"
                            >
                                {{ module.label }}
                            </UBadge>
                        </div>
                    </template>

                    <template #created_at-cell="{ row }">
                        <span v-if="row.original.created_at">{{
                            formatDateTime(row.original.created_at, timezone)
                        }}</span>
                    </template>

                    <template #actions-cell="{ row }">
                        <RowActions
                            v-if="row.original.actions"
                            :actions="row.original.actions"
                        />
                    </template>
                </UTable>

                <template v-if="tenants.meta.last_page > 1">
                    <div class="mt-4 flex justify-end">
                        <UPagination
                            :items-per-page="tenants.meta.per_page"
                            :page="tenants.meta.current_page"
                            :total="tenants.meta.total"
                            @update:page="(page: number) => search(page)"
                        />
                    </div>
                </template>
            </div>
        </template>
    </UDashboardPanel>
</template>
