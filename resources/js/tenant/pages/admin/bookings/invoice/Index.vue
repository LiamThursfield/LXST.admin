<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { index } from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/InvoiceController';
import type { DocumentRow, SelectItem } from '@/types';

const props = defineProps<{
    filter?: { status?: string; search?: string };
    columns: Array<{ accessorKey: string; header: string }>;
    documents: {
        data: DocumentRow[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
    statuses: SelectItem[];
}>();

const { trans } = useTrans();

/** 'any' means no filter for the select input. */
const statusItems = computed(() => [
    { label: trans('bookings.documents.all_statuses'), value: 'any' },
    ...props.statuses,
]);

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => index.url(),
    initial: {
        search: props.filter?.search ?? '',
        status: props.filter?.status ?? 'any',
    },
    blanks: { status: 'any' },
    labels: {
        status: {
            label: trans('bookings.documents.columns.status'),
            items: () => props.statuses,
        },
    },
});

const statusColours: Record<
    string,
    'success' | 'warning' | 'error' | 'info' | 'neutral'
> = {
    sent: 'info',
    accepted: 'success',
    paid: 'success',
    overdue: 'error',
    declined: 'warning',
};
</script>

<template>
    <ListLayout
        v-model:search="filters.search"
        :title="trans('bookings.documents.invoices_title')"
        :columns="columns"
        :rows="documents.data"
        :pagination="documents.meta"
        :search-placeholder="trans('bookings.documents.search')"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('bookings.documents.invoices_empty')"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template #filters>
            <USelect
                v-model="filters.status"
                class="w-full sm:w-44"
                :items="statusItems"
                :aria-label="trans('bookings.documents.columns.status')"
            />
        </template>

        <template #number-cell="{ row }">
            <Link :href="row.original.show_url" class="text-primary">
                {{ row.original.number }}
            </Link>
        </template>

        <template #date-cell="{ row }">
            {{ row.original.date ?? '–' }}
        </template>

        <template #status_label-cell="{ row }">
            <UBadge
                :color="statusColours[row.original.status] ?? 'neutral'"
                variant="subtle"
                :label="row.original.status_label"
            />
        </template>
    </ListLayout>
</template>
