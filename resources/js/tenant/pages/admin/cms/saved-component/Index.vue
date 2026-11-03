<script setup lang="ts">
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Cms/SavedComponentController';
import RowActions from '@/components/RowActions.vue';
import type { SavedComponentData, TemplateData } from '@/types';

const props = defineProps<{
    filter?: {
        name?: string;
        template_id?: string;
    };
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    savedComponents: {
        data: SavedComponentData[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
    templates: { data: TemplateData[] };
    canCreate: boolean;
}>();

const { trans } = useTrans();

const templateItems = computed(() =>
    props.templates.data.map((template) => ({
        label: template.name,
        value: String(template.id),
    })),
);

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => index.url(),
    initial: {
        name: props.filter?.name || '',
        template_id: props.filter?.template_id
            ? String(props.filter.template_id)
            : '',
    },
    labels: {
        template_id: {
            label: trans('cms.saved_components.filters.template'),
            items: templateItems,
        },
    },
});
</script>

<template>
    <ListLayout
        v-model:search="filters.name"
        :title="trans('cms.saved_components.index.title')"
        :columns="columns"
        :rows="savedComponents.data"
        :pagination="savedComponents.meta"
        :search-placeholder="trans('cms.saved_components.filters.name')"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('cms.saved_components.index.empty')"
        :create-label="
            canCreate ? trans('cms.saved_components.index.create') : undefined
        "
        :create-href="canCreate ? create.url() : undefined"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template #filters>
            <USelect
                class="w-full sm:w-48"
                :items="templateItems"
                :placeholder="trans('cms.saved_components.filters.template')"
                v-model="filters.template_id"
            />
        </template>

        <template #template-cell="{ row }">
            {{ row.original.template?.name }}
        </template>

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
