<script setup lang="ts">
import ResourceController from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/ResourceController';
import StaffController from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/StaffController';
import RowActions from '@/components/RowActions.vue';
import type { BookableResourceData, SelectItem } from '@/types';

const props = defineProps<{
    filter?: {
        name?: string;
        type?: string;
    };
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    resources: {
        data: BookableResourceData[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
    types: SelectItem[];
    forStaff: boolean;
    canCreate: boolean;
}>();

const { trans } = useTrans();

const controller = props.forStaff ? StaffController : ResourceController;
const copy = props.forStaff ? 'bookings.staff' : 'bookings.resources';

/** 'any' means no filter for the select input. */
const typeItems = computed(() => [
    { label: trans('bookings.resources.index.all_types'), value: 'any' },
    ...props.types,
]);

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => controller.index.url(),
    initial: {
        name: props.filter?.name || '',
        type: props.filter?.type ?? 'any',
    },
    blanks: { type: 'any' },
    labels: {
        type: {
            label: trans('bookings.resources.columns.type'),
            items: () => props.types,
        },
    },
});
</script>

<template>
    <ListLayout
        v-model:search="filters.name"
        :title="trans(`${copy}.index.title`)"
        :columns="columns"
        :rows="resources.data"
        :pagination="resources.meta"
        :search-placeholder="trans('bookings.resources.fields.name')"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans(`${copy}.index.empty`)"
        :create-label="canCreate ? trans(`${copy}.index.create`) : undefined"
        :create-href="canCreate ? controller.create.url() : undefined"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template v-if="!forStaff" #filters>
            <USelect
                v-model="filters.type"
                class="w-full sm:w-44"
                :items="typeItems"
                :aria-label="trans('bookings.resources.columns.type')"
            />
        </template>

        <template #is_active-cell="{ row }">
            {{
                trans(
                    row.original.is_active
                        ? 'bookings.resources.active.yes'
                        : 'bookings.resources.active.no',
                )
            }}
        </template>

        <template #actions-cell="{ row }">
            <RowActions
                v-if="row.original.actions"
                :actions="row.original.actions"
            />
        </template>
    </ListLayout>
</template>
