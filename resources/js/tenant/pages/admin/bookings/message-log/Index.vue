<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import MessageLogController from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/MessageLogController';
import type { SelectItem } from '@/types';

type LogRow = {
    id: number;
    when: string | null;
    customer_name: string | null;
    booking_url: string | null;
    event_label: string;
    channel_label: string;
    recipient: string | null;
    status: string;
    status_label: string;
    note: string | null;
};

const props = defineProps<{
    filter?: { status?: string };
    columns: Array<{ accessorKey: string; header: string }>;
    logs: {
        data: LogRow[];
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
    { label: trans('bookings.messages.log.all_statuses'), value: 'any' },
    ...props.statuses,
]);

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => MessageLogController.url(),
    initial: { status: props.filter?.status ?? 'any' },
    blanks: { status: 'any' },
    labels: {
        status: {
            label: trans('bookings.messages.log.columns.status'),
            items: () => props.statuses,
        },
    },
});
</script>

<template>
    <ListLayout
        :title="trans('bookings.messages.log.title')"
        :columns="columns"
        :rows="logs.data"
        :pagination="logs.meta"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('bookings.messages.log.empty')"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template #filters>
            <USelect
                v-model="filters.status"
                class="w-full sm:w-44"
                :items="statusItems"
                :aria-label="trans('bookings.messages.log.columns.status')"
            />
        </template>

        <template #customer_name-cell="{ row }">
            <Link
                v-if="row.original.booking_url"
                :href="row.original.booking_url"
                class="text-primary"
            >
                {{ row.original.customer_name }}
            </Link>
        </template>

        <template #status_label-cell="{ row }">
            <UBadge
                :color="
                    row.original.status === 'failed'
                        ? 'error'
                        : row.original.status === 'sent'
                          ? 'success'
                          : 'neutral'
                "
                variant="subtle"
                :label="row.original.status_label"
            />
        </template>
    </ListLayout>
</template>
