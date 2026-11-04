<script setup lang="ts">
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/ServiceCategoryController';
import RowActions from '@/components/RowActions.vue';
import type { ServiceCategoryData } from '@/types';

const props = defineProps<{
    filter?: {
        name?: string;
    };
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    categories: {
        data: ServiceCategoryData[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
    canCreate: boolean;
}>();

const { trans } = useTrans();

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => index.url(),
    initial: {
        name: props.filter?.name || '',
    },
});
</script>

<template>
    <ListLayout
        v-model:search="filters.name"
        :title="trans('bookings.categories.index.title')"
        :columns="columns"
        :rows="categories.data"
        :pagination="categories.meta"
        :search-placeholder="trans('bookings.categories.fields.name')"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('bookings.categories.index.empty')"
        :create-label="
            canCreate ? trans('bookings.categories.index.create') : undefined
        "
        :create-href="canCreate ? create.url() : undefined"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template #actions-cell="{ row }">
            <RowActions
                v-if="row.original.actions"
                :actions="row.original.actions"
            />
        </template>
    </ListLayout>
</template>
