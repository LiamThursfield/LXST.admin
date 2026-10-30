<script setup lang="ts">
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Cms/MenuController';
import RowActions from '@/components/RowActions.vue';
import type { MenuData } from '@/types';

const props = defineProps<{
    filter?: {
        name?: string;
    };
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    cmsMenus: {
        data: MenuData[];
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
        :title="trans('cms.menus.index.title')"
        :columns="columns"
        :rows="cmsMenus.data"
        :pagination="cmsMenus.meta"
        :search-placeholder="trans('cms.menus.filters.name')"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('cms.menus.index.empty')"
        :create-label="canCreate ? trans('cms.menus.index.create') : undefined"
        :create-href="canCreate ? create.url() : undefined"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template #slug-cell="{ row }">
            <span class="font-mono">{{ row.original.slug }}</span>
        </template>

        <template #actions-cell="{ row }">
            <RowActions
                v-if="row.original.actions"
                :actions="row.original.actions"
            />
        </template>
    </ListLayout>
</template>
