<script setup lang="ts">
import { show as importPage } from '@/actions/App/Http/Controllers/Tenant/Admin/Cms/ContentImportController';
import {
    create,
    exportMethod,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Cms/LayoutController';
import RowActions from '@/components/RowActions.vue';
import ExportActions from '@/tenant/components/cms/ExportActions.vue';
import type { LayoutData, TemplateData } from '@/types';

const props = defineProps<{
    filter?: {
        name?: string;
        template_id?: string;
    };
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    layouts: {
        data: LayoutData[];
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

const selected = ref<Array<string | number>>([]);

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
            label: trans('cms.layouts.filters.template'),
            items: templateItems,
        },
    },
});
</script>

<template>
    <ListLayout
        v-model:search="filters.name"
        v-model:selected="selected"
        selectable
        :title="trans('cms.layouts.index.title')"
        :columns="columns"
        :rows="layouts.data"
        :pagination="layouts.meta"
        :search-placeholder="trans('cms.layouts.filters.name')"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('cms.layouts.index.empty')"
        :create-label="
            canCreate ? trans('cms.layouts.index.create') : undefined
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
                filename="layouts.json"
            />
        </template>

        <template #filters>
            <USelect
                class="w-full sm:w-48"
                :items="templateItems"
                :placeholder="trans('cms.layouts.filters.template')"
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
