<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    create,
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

const searchFilter = ref({
    name: props.filter?.name || '',
    type: props.filter?.type || '',
});

const search = useDebounceFn((page: number = 1) => {
    const query = new URLSearchParams();
    const filter: Record<string, string> = {};

    const newValue = searchFilter.value;

    if (newValue.name.length) {
        query.append('filter[name]', newValue.name);
        filter.name = newValue.name;
    }

    if (newValue.type.length) {
        query.append('filter[type]', newValue.type);
        filter.type = newValue.type;
    }

    let url = `${window.location.pathname}`;
    if (query.size > 0) {
        url += `?${query.toString()}`;
    }
    window.history.pushState({}, '', url);

    router.get(
        index.url(),
        {
            page,
            filter,
        },
        { preserveState: true, replace: true },
    );
}, 300);

watch(searchFilter, () => search(), { deep: true });
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('cms.templates.index.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>

                <template #right>
                    <UButton
                        v-if="canCreate"
                        :as="Link"
                        :href="create.url()"
                        color="primary"
                    >
                        <UIcon name="i-lucide-plus" />
                        {{ trans('cms.templates.index.create') }}
                    </UButton>
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('cms.templates.index.title')" />
            <div class="flex flex-col gap-8">
                <div
                    class="flex flex-wrap items-center justify-between gap-8 px-2 py-4"
                >
                    <div class="flex flex-1 items-center gap-4">
                        <UInput
                            class="max-w-64"
                            icon="i-lucide-search"
                            :placeholder="trans('cms.templates.filters.name')"
                            v-model="searchFilter.name"
                        />
                        <USelect
                            class="w-full max-w-40"
                            :items="types"
                            :placeholder="trans('cms.templates.filters.type')"
                            v-model="searchFilter.type"
                        />
                    </div>
                </div>

                <UTable
                    :columns="columns"
                    :data="templates.data"
                    :empty="trans('cms.templates.index.empty')"
                >
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
                </UTable>

                <template v-if="templates.meta.last_page > 1">
                    <div class="mt-4 flex justify-end">
                        <UPagination
                            :items-per-page="templates.meta.per_page"
                            :model-value="templates.meta.current_page"
                            :total="templates.meta.total"
                            @update:page="(page: any) => search(page)"
                        />
                    </div>
                </template>
            </div>
        </template>
    </UDashboardPanel>
</template>
