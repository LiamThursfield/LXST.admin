<script setup lang="ts">
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/WaitlistController';
import RowActions from '@/components/RowActions.vue';
import type { SelectItem, WaitlistEntryData } from '@/types';

const props = defineProps<{
    filter?: {
        status?: string;
        session_id?: string;
    };
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    entries: {
        data: WaitlistEntryData[];
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
    { label: trans('bookings.waitlist.index.all_statuses'), value: 'any' },
    ...props.statuses,
]);

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => index.url(),
    initial: {
        status: props.filter?.status ?? 'waiting',
        session_id: props.filter?.session_id ?? '',
    },
    blanks: { status: 'any' },
    labels: {
        status: {
            label: trans('bookings.waitlist.columns.status'),
            items: () => props.statuses,
        },
    },
});
</script>

<template>
    <ListLayout
        :title="trans('bookings.waitlist.index.title')"
        :columns="columns"
        :rows="entries.data"
        :pagination="entries.meta"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('bookings.waitlist.index.empty')"
        :create-label="
            canCreate ? trans('bookings.waitlist.index.create') : undefined
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
                :aria-label="trans('bookings.waitlist.columns.status')"
            />
        </template>

        <template #status_label-cell="{ row }">
            <div class="flex items-center gap-2">
                {{ row.original.status_label }}
                <UBadge
                    v-if="row.original.place_free"
                    color="success"
                    variant="subtle"
                    :label="trans('bookings.waitlist.place_free')"
                />
            </div>
        </template>

        <template #actions-cell="{ row }">
            <RowActions
                v-if="row.original.actions"
                :actions="row.original.actions"
            />
        </template>
    </ListLayout>
</template>
