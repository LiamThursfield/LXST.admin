<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { index } from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/BookingPaymentController';
import type { PaymentRow, SelectItem } from '@/types';

const props = defineProps<{
    filter?: { status?: string };
    columns: Array<{ accessorKey: string; header: string }>;
    payments: {
        data: PaymentRow[];
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
    { label: trans('bookings.payments.all_statuses'), value: 'any' },
    ...props.statuses,
]);

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => index.url(),
    initial: { status: props.filter?.status ?? 'any' },
    blanks: { status: 'any' },
    labels: {
        status: {
            label: trans('bookings.payments.columns.status'),
            items: () => props.statuses,
        },
    },
});
</script>

<template>
    <ListLayout
        :title="trans('bookings.payments.title')"
        :columns="columns"
        :rows="payments.data"
        :pagination="payments.meta"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('bookings.payments.empty')"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template #filters>
            <USelect
                v-model="filters.status"
                class="w-full sm:w-44"
                :items="statusItems"
                :aria-label="trans('bookings.payments.columns.status')"
            />
        </template>

        <template #customer_name-cell="{ row }">
            <Link :href="row.original.booking_url" class="text-primary">
                {{ row.original.customer_name }}
            </Link>
        </template>

        <template #status_label-cell="{ row }">
            <UBadge
                :color="row.original.status === 'paid' ? 'success' : 'neutral'"
                variant="subtle"
                :label="row.original.status_label"
            />
        </template>
    </ListLayout>
</template>
