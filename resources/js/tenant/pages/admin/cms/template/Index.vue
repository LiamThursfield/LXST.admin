<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { show as importPage } from '@/actions/App/Http/Controllers/Tenant/Admin/Cms/ContentImportController';
import {
    create,
    exportMethod,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Cms/TemplateController';
import RowActions from '@/components/RowActions.vue';
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

const toast = useToast();

const selected = ref<Array<string | number>>([]);

const exportRequest = useHttp();

/** The ticked templates and those they nest, as JSON. */
async function exportedJson(): Promise<string | null> {
    try {
        const exported = await exportRequest.get(
            exportMethod.url({ query: { ids: selected.value } }),
        );

        return JSON.stringify(exported, null, 2);
    } catch {
        toast.add({
            title: trans('cms.templates.index.export_failed'),
            icon: 'i-lucide-circle-alert',
            color: 'error',
        });

        return null;
    }
}

async function copyJson() {
    const json = await exportedJson();

    if (json !== null) {
        await navigator.clipboard.writeText(json);
        toast.add({
            title: trans('cms.templates.index.copied'),
            icon: 'i-lucide-check',
            color: 'success',
        });
    }
}

async function downloadJson() {
    const json = await exportedJson();

    if (json !== null) {
        const url = URL.createObjectURL(
            new Blob([json], { type: 'application/json' }),
        );
        const link = document.createElement('a');

        link.href = url;
        link.download = 'templates.json';
        link.click();
        URL.revokeObjectURL(url);
    }
}

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
                :label="trans('cms.templates.index.import')"
            />
        </template>

        <template #bulk-actions>
            <UButton
                color="neutral"
                variant="subtle"
                size="sm"
                icon="i-lucide-copy"
                :loading="exportRequest.processing"
                :label="trans('cms.templates.index.copy_json')"
                @click="copyJson"
            />
            <UButton
                color="neutral"
                variant="subtle"
                size="sm"
                icon="i-lucide-download"
                :loading="exportRequest.processing"
                :label="trans('cms.templates.index.download_json')"
                @click="downloadJson"
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
