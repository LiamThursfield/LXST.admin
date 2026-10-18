<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Cms/LayoutController';
import RowActions from '@/components/RowActions.vue';
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

const templateItems = computed(() =>
    props.templates.data.map((template) => ({
        label: template.name,
        value: template.id,
    })),
);

const searchFilter = ref<{ name: string; template_id?: number }>({
    name: props.filter?.name || '',
    template_id: props.filter?.template_id
        ? Number(props.filter.template_id)
        : undefined,
});

const search = useDebounceFn((page: number = 1) => {
    const query = new URLSearchParams();
    const filter: Record<string, string> = {};

    const newValue = searchFilter.value;

    if (newValue.name.length) {
        query.append('filter[name]', newValue.name);
        filter.name = newValue.name;
    }

    if (newValue.template_id) {
        query.append('filter[template_id]', String(newValue.template_id));
        filter.template_id = String(newValue.template_id);
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
            <UDashboardNavbar :title="trans('cms.layouts.index.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>

                <template #right>
                    <UButton
                        v-if="canCreate"
                        as="Link"
                        :href="create.url()"
                        color="primary"
                    >
                        <UIcon name="i-lucide-plus" />
                        {{ trans('cms.layouts.index.create') }}
                    </UButton>
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('cms.layouts.index.title')" />
            <div class="flex flex-col gap-8">
                <div
                    class="flex flex-wrap items-center justify-between gap-8 px-2 py-4"
                >
                    <div class="flex flex-1 items-center gap-4">
                        <UInput
                            class="max-w-64"
                            icon="i-lucide-search"
                            :placeholder="trans('cms.layouts.filters.name')"
                            v-model="searchFilter.name"
                        />
                        <USelect
                            class="w-full max-w-48"
                            :items="templateItems"
                            :placeholder="trans('cms.layouts.filters.template')"
                            v-model="searchFilter.template_id"
                        />
                    </div>
                </div>

                <UTable
                    :columns="columns"
                    :data="layouts.data"
                    :empty="trans('cms.layouts.index.empty')"
                >
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
                </UTable>

                <template v-if="layouts.meta.last_page > 1">
                    <div class="mt-4 flex justify-end">
                        <UPagination
                            :items-per-page="layouts.meta.per_page"
                            :page="layouts.meta.current_page"
                            :total="layouts.meta.total"
                            @update:page="(page: any) => search(page)"
                        />
                    </div>
                </template>
            </div>
        </template>
    </UDashboardPanel>
</template>
