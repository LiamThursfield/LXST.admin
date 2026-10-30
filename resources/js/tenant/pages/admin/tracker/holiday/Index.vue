<script setup lang="ts">
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Tracker/HolidayController';
import RowActions from '@/components/RowActions.vue';

const { trans } = useTrans();

const props = defineProps<{
    filter?: {
        label?: string;
    };
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    holidays: {
        data: Array<{
            id: number;
            label?: string | null;
            starts_on: string;
            ends_on: string;
            actions?: any[];
        }>;
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
}>();

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => index.url(),
    initial: {
        label: props.filter?.label || '',
    },
});
</script>

<template>
    <ListLayout
        v-model:search="filters.label"
        :title="trans('tracker.holidays.index.title')"
        :columns="columns"
        link-column="label"
        :rows="holidays.data"
        :pagination="holidays.meta"
        :search-placeholder="trans('tracker.holidays.fields.label')"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('tracker.holidays.index.empty')"
        :create-label="trans('tracker.holidays.index.create')"
        :create-href="create.url()"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template #label-cell="{ row }">
            <ListRecordLink :actions="row.original.actions">
                {{
                    row.original.label ||
                    `${row.original.starts_on} – ${row.original.ends_on}`
                }}
            </ListRecordLink>
        </template>

        <template #actions-cell="{ row }">
            <RowActions
                v-if="row.original.actions"
                :actions="row.original.actions"
            />
        </template>
    </ListLayout>
</template>
