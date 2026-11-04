<script setup lang="ts">
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/TimeOffController';
import RowActions from '@/components/RowActions.vue';
import type { SelectItem, TimeOffData } from '@/types';

const props = defineProps<{
    filter?: {
        status?: string;
    };
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    timeOff: {
        data: TimeOffData[];
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
    { label: trans('bookings.time_off.index.all_statuses'), value: 'any' },
    ...props.statuses,
]);

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => index.url(),
    initial: {
        status: props.filter?.status ?? 'any',
    },
});
</script>

<template>
    <ListLayout
        :title="trans('bookings.time_off.index.title')"
        :columns="columns"
        :rows="timeOff.data"
        :pagination="timeOff.meta"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('bookings.time_off.index.empty')"
        :create-label="
            canCreate ? trans('bookings.time_off.index.create') : undefined
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
                :aria-label="trans('bookings.time_off.columns.status')"
            />
        </template>

        <template #actions-cell="{ row }">
            <RowActions
                v-if="row.original.actions"
                :actions="row.original.actions"
            />
        </template>
    </ListLayout>
</template>
