<script setup lang="ts">
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Crm/FormController';
import RowActions from '@/components/RowActions.vue';
import type { CrmFormData } from '@/types';

const props = defineProps<{
    filter?: {
        name?: string;
    };
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    forms: {
        data: CrmFormData[];
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
        :title="trans('crm.forms.index.title')"
        :columns="columns"
        :rows="forms.data"
        :pagination="forms.meta"
        :search-placeholder="trans('crm.forms.fields.name')"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('crm.forms.index.empty')"
        :create-label="canCreate ? trans('crm.forms.index.create') : undefined"
        :create-href="canCreate ? create.url() : undefined"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template #slug-cell="{ row }">
            <code class="text-muted text-sm">{{ row.original.slug }}</code>
        </template>

        <template #actions-cell="{ row }">
            <RowActions
                v-if="row.original.actions"
                :actions="row.original.actions"
            />
        </template>
    </ListLayout>
</template>
