<script setup lang="ts">
import { show as importPage } from '@/actions/App/Http/Controllers/Tenant/Admin/Cms/ContentImportController';
import {
    create,
    exportMethod,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Cms/TemplateController';
import RowActions from '@/components/RowActions.vue';
import ExportActions from '@/tenant/components/cms/ExportActions.vue';
import type { SelectItem, TemplateData } from '@/types';

const props = defineProps<{
    filter?: {
        name?: string;
        type?: string;
    };
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    templates: {
        data: TemplateData[];
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

const selected = ref<Array<string | number>>([]);

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => index.url(),
    initial: {
        name: props.filter?.name || '',
        type: props.filter?.type || '',
    },
    labels: {
        type: {
            label: trans('cms.templates.filters.type'),
            items: () => props.types,
        },
    },
});
</script>

<template>
    <ListLayout
        v-model:search="filters.name"
        v-model:selected="selected"
        selectable
        :title="trans('cms.templates.index.title')"
        :columns="columns"
        :rows="templates.data"
        :pagination="templates.meta"
        :search-placeholder="trans('cms.templates.filters.name')"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('cms.templates.index.empty')"
        :create-label="
            canCreate ? trans('cms.templates.index.create') : undefined
        "
        :create-href="canCreate ? create.url() : undefined"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template v-if="canCreate" #actions>
            <UButton
                as="Link"
                :href="importPage.url()"
                color="neutral"
                variant="subtle"
                icon="i-lucide-upload"
                :label="trans('cms.export.import')"
            />
        </template>

        <template #bulk-actions>
            <ExportActions
                :url="() => exportMethod.url({ query: { ids: selected } })"
                filename="templates.json"
            />
        </template>

        <template #filters>
            <USelect
                class="w-full sm:w-40"
                :items="types"
                :placeholder="trans('cms.templates.filters.type')"
                v-model="filters.type"
            />
        </template>

        <template #type-cell="{ row }">
            <UBadge color="neutral" variant="subtle">
                {{ row.original.type_label }}
            </UBadge>
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
