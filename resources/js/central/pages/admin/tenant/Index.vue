<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    create,
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

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => index.url(),
    initial: {
        search: props.filter?.search ?? '',
        module: props.filter?.module ?? '',
    },
    labels: {
        module: {
            label: trans('tenants.filters.module'),
            items: () => props.modules,
        },
    },
    extra: () => (sort.value !== 'name' ? { sort: sort.value } : {}),
});
</script>

<template>
    <ListLayout
        v-model:search="filters.search"
        :title="trans('tenants.index.title')"
        :columns="columns"
        :rows="tenants.data"
        :pagination="tenants.meta"
        :search-placeholder="trans('tenants.filters.search')"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('tenants.index.empty')"
        :create-label="trans('tenants.index.create')"
        :create-href="create.url()"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template #filters>
            <USelect
                v-model="filters.module"
                class="w-full sm:w-40"
                :items="modules"
                :placeholder="trans('tenants.filters.module')"
            />
        </template>

        <template v-for="key in sortableColumns" :key="key" #[`${key}-header`]>
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
            <div class="flex items-center gap-2">
                <Link
                    :href="show.url(row.original.id)"
                    class="text-highlighted font-medium hover:underline"
                >
                    {{ row.original.name }}
                </Link>
                <UBadge
                    v-if="row.original.is_disabled"
                    color="warning"
                    variant="subtle"
                >
                    {{ trans('tenants.status.disabled') }}
                </UBadge>
            </div>
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
    </ListLayout>
</template>
