<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import type { DropdownMenuItem } from '@nuxt/ui';
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Catalogue/TagController';
import { store as storePresets } from '@/actions/App/Http/Controllers/Tenant/Admin/Catalogue/TagPresetController';
import RowActions from '@/components/RowActions.vue';
import type { CatalogueTagData, CatalogueTagPresetGroup } from '@/types';

const props = defineProps<{
    filter?: {
        name?: string;
    };
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    tags: {
        data: CatalogueTagData[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
    /** The built-in groups, such as the allergens. Empty for those who can't add tags. */
    presetGroups: CatalogueTagPresetGroup[];
    canCreate: boolean;
}>();

const { trans } = useTrans();

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => index.url(),
    initial: {
        name: props.filter?.name || '',
    },
});

const adding = ref(false);

function addGroup(group: string) {
    router.post(
        storePresets.url(),
        { group },
        {
            preserveScroll: true,
            onStart: () => (adding.value = true),
            onFinish: () => (adding.value = false),
        },
    );
}

/** The industry's suggested groups first. */
const presetItems = computed<DropdownMenuItem[]>(() =>
    [...props.presetGroups]
        .sort((a, b) => Number(b.suggested) - Number(a.suggested))
        .map((group) => ({
            label: group.label,
            description: [
                group.suggested
                    ? trans('catalogue.tags.presets.suggested')
                    : null,
                group.missing
                    ? trans('catalogue.tags.presets.missing', {
                          count: String(group.missing),
                      })
                    : trans('catalogue.tags.presets.all_added'),
            ]
                .filter(Boolean)
                .join(' · '),
            disabled: group.missing === 0,
            onSelect: () => addGroup(group.key),
        })),
);
</script>

<template>
    <ListLayout
        v-model:search="filters.name"
        :title="trans('catalogue.tags.index.title')"
        :columns="columns"
        :rows="tags.data"
        :pagination="tags.meta"
        :search-placeholder="trans('catalogue.tags.index.search')"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('catalogue.tags.index.empty')"
        :create-label="
            canCreate ? trans('catalogue.tags.index.create') : undefined
        "
        :create-href="canCreate ? create.url() : undefined"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template v-if="presetGroups.length" #actions>
            <UDropdownMenu :items="presetItems">
                <UButton
                    color="neutral"
                    variant="subtle"
                    icon="i-lucide-list-plus"
                    trailing-icon="i-lucide-chevron-down"
                    :loading="adding"
                    :label="trans('catalogue.tags.presets.add')"
                />
            </UDropdownMenu>
        </template>

        <template #name-cell="{ row }">
            <span class="flex items-center gap-2">
                <UIcon
                    v-if="row.original.icon"
                    :name="row.original.icon"
                    class="text-muted size-4"
                />
                {{ row.original.name }}
            </span>
        </template>

        <template #actions-cell="{ row }">
            <RowActions
                v-if="row.original.actions"
                :actions="row.original.actions"
            />
        </template>
    </ListLayout>
</template>
