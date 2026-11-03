<script setup lang="ts">
import { show as importPage } from '@/actions/App/Http/Controllers/Tenant/Admin/Cms/ContentImportController';
import {
    create,
    exportMethod,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Crm/FormController';
import RowActions from '@/components/RowActions.vue';
import ExportActions from '@/tenant/components/cms/ExportActions.vue';
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
    canImport: boolean;
}>();

const { trans } = useTrans();

const selected = ref<Array<string | number>>([]);

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
        v-model:selected="selected"
        selectable
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
        <template v-if="canImport" #actions>
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
                filename="forms.json"
            />
        </template>

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
