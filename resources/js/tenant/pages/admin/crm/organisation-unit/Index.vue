<script setup lang="ts">
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Crm/OrganisationUnitController';
import RowActions from '@/components/RowActions.vue';
import type { OrganisationUnitData, SelectItem } from '@/types';

const props = defineProps<{
    filter?: {
        name?: string;
        type?: string;
    };
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    organisationUnits: {
        data: OrganisationUnitData[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
    types: SelectItem[];
    canCreate: boolean;
}>();

const { trans } = useTrans();

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => index.url(),
    initial: {
        name: props.filter?.name || '',
        type: props.filter?.type || '',
    },
    labels: {
        type: {
            label: trans('crm.organisation_units.filters.type'),
            items: () => props.types,
        },
    },
});
</script>

<template>
    <ListLayout
        v-model:search="filters.name"
        :title="trans('crm.organisation_units.index.title')"
        :columns="columns"
        :rows="organisationUnits.data"
        :pagination="organisationUnits.meta"
        :search-placeholder="trans('crm.organisation_units.filters.name')"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('crm.organisation_units.index.empty')"
        :create-label="
            canCreate ? trans('crm.organisation_units.index.create') : undefined
        "
        :create-href="canCreate ? create.url() : undefined"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template #filters>
            <USelect
                class="w-full max-w-40"
                :items="types"
                :placeholder="trans('crm.organisation_units.filters.type')"
                v-model="filters.type"
            />
        </template>

        <template #type-cell="{ row }">
            <UBadge color="neutral" variant="subtle">
                {{ row.original.type_label }}
            </UBadge>
        </template>

        <template #parent-cell="{ row }">
            <span v-if="row.original.parent">{{
                row.original.parent.name
            }}</span>
            <span v-else class="text-gray-400">-</span>
        </template>

        <template #actions-cell="{ row }">
            <RowActions
                v-if="row.original.actions"
                :actions="row.original.actions"
            />
        </template>
    </ListLayout>
</template>
