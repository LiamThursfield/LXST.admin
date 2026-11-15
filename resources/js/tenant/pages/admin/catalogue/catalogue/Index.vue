<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Catalogue/CatalogueController';
import { index as qrCode } from '@/actions/App/Http/Controllers/Tenant/Admin/Catalogue/CatalogueQrCodeController';
import RowActions from '@/components/RowActions.vue';
import CatalogueShare from '@/tenant/components/catalogue/CatalogueShare.vue';
import type { CatalogueData } from '@/types';

const props = defineProps<{
    filter?: {
        name?: string;
    };
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    catalogues: {
        data: CatalogueData[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
    canCreate: boolean;
    /** The public page listing every published catalogue. */
    publicUrl: string;
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
        :title="trans('catalogue.catalogues.index.title')"
        :columns="columns"
        :rows="catalogues.data"
        :pagination="catalogues.meta"
        :search-placeholder="trans('catalogue.catalogues.index.search')"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('catalogue.catalogues.index.empty')"
        :create-label="
            canCreate ? trans('catalogue.catalogues.index.create') : undefined
        "
        :create-href="canCreate ? create.url() : undefined"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template #actions>
            <UModal
                :title="trans('catalogue.share.all_title')"
                :description="trans('catalogue.share.all_help')"
            >
                <UButton
                    icon="i-lucide-qr-code"
                    color="neutral"
                    variant="outline"
                    :label="trans('catalogue.share.button')"
                    :ui="{ label: 'hidden sm:inline' }"
                />

                <template #body>
                    <CatalogueShare
                        :url="publicUrl"
                        :qr-code-url="qrCode.url()"
                        :download-url="qrCode.url({ query: { download: '1' } })"
                    />
                </template>
            </UModal>
        </template>

        <template #name-cell="{ row }">
            <Link :href="row.original.url" class="text-highlighted font-medium">
                {{ row.original.name }}
            </Link>
        </template>

        <template #schedule-cell="{ row }">
            {{ row.original.schedule ?? trans('catalogue.catalogues.always') }}
        </template>

        <template #is_published-cell="{ row }">
            <div class="flex flex-wrap gap-1">
                <UBadge
                    :color="row.original.is_published ? 'success' : 'neutral'"
                    variant="subtle"
                    :label="
                        trans(
                            row.original.is_published
                                ? 'catalogue.catalogues.status.published'
                                : 'catalogue.catalogues.status.draft',
                        )
                    "
                />
                <UBadge
                    v-if="row.original.applies_now"
                    color="primary"
                    variant="subtle"
                    icon="i-lucide-clock"
                    :label="trans('catalogue.catalogues.status.applies_now')"
                />
            </div>
        </template>

        <template #actions-cell="{ row }">
            <RowActions
                v-if="row.original.actions"
                :actions="row.original.actions"
            />
        </template>
    </ListLayout>
</template>
