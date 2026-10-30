<script setup lang="ts">
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Tracker/TaskDefinitionController';
import RowActions from '@/components/RowActions.vue';

const { trans } = useTrans();

const props = defineProps<{
    filter?: {
        title?: string;
        status?: string;
    };
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    tasks: {
        data: Array<{
            id: number;
            title: string;
            period: string;
            status: string;
            actions?: any[];
        }>;
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
    statuses: Array<{ label: string; value: string }>;
}>();

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => index.url(),
    initial: {
        title: props.filter?.title || '',
        status: props.filter?.status || '',
    },
    labels: {
        status: {
            label: trans('tracker.tasks.fields.status'),
            items: () => props.statuses,
        },
    },
});
</script>

<template>
    <ListLayout
        v-model:search="filters.title"
        :title="trans('tracker.tasks.index.title')"
        :columns="columns"
        link-column="title"
        :rows="tasks.data"
        :pagination="tasks.meta"
        :search-placeholder="trans('tracker.tasks.fields.title')"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('tracker.tasks.index.empty')"
        :create-label="trans('tracker.tasks.index.create')"
        :create-href="create.url()"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template #filters>
            <USelect
                class="w-full sm:w-40"
                :items="statuses"
                :placeholder="trans('tracker.tasks.fields.status')"
                v-model="filters.status"
            />
        </template>

        <template #status-cell="{ row }">
            <UBadge color="neutral" variant="subtle">
                {{ row.original.status }}
            </UBadge>
        </template>

        <template #actions-cell="{ row }">
            <RowActions
                v-if="row.original.actions"
                :actions="row.original.actions"
            />
        </template>
    </ListLayout>
</template>
