<script setup lang="ts">
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/BookingController';
import RowActions from '@/components/RowActions.vue';
import type { BookingData, SelectItem } from '@/types';

const props = defineProps<{
    filter?: {
        customer_name?: string;
        status?: string;
    };
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    bookings: {
        data: BookingData[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
    statuses: SelectItem[];
    canCreate: boolean;
}>();

const { trans } = useTrans();

/** 'any' means no filter for the select input. */
const statusItems = computed(() => [
    { label: trans('bookings.bookings.index.all_statuses'), value: 'any' },
    ...props.statuses,
]);

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => index.url(),
    initial: {
        customer_name: props.filter?.customer_name || '',
        status: props.filter?.status ?? 'any',
    },
    blanks: { status: 'any' },
    labels: {
        status: {
            label: trans('bookings.bookings.columns.status'),
            items: () => props.statuses,
        },
    },
});
</script>

<template>
    <ListLayout
        v-model:search="filters.customer_name"
        :title="trans('bookings.bookings.index.title')"
        :columns="columns"
        :rows="bookings.data"
        :pagination="bookings.meta"
        :search-placeholder="trans('bookings.bookings.columns.customer')"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('bookings.bookings.index.empty')"
        :create-label="
            canCreate ? trans('bookings.bookings.index.create') : undefined
        "
        :create-href="canCreate ? create.url() : undefined"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template #filters>
            <USelect
                v-model="filters.status"
                class="w-full sm:w-44"
                :items="statusItems"
                :aria-label="trans('bookings.bookings.columns.status')"
            />
        </template>

        <template #when-cell="{ row }">
            {{
                row.original.when ??
                trans('bookings.bookings.fields.no_time_yet')
            }}
        </template>

        <template #resource_names-cell="{ row }">
            {{ row.original.resource_names?.join(', ') }}
        </template>

        <template #actions-cell="{ row }">
            <RowActions
                v-if="row.original.actions"
                :actions="row.original.actions"
            />
        </template>
    </ListLayout>
</template>
