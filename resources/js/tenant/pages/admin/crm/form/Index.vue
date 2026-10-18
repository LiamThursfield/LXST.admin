<script setup lang="ts">
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Crm/FormController';
import RowActions from '@/components/RowActions.vue';
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
}>();

const { trans } = useTrans();

const searchFilter = ref({
    name: props.filter?.name || '',
});

const search = useDebounceFn((page: number = 1) => {
    const query = new URLSearchParams();
    const filter: Record<string, string> = {};

    if (searchFilter.value.name.length) {
        query.append('filter[name]', searchFilter.value.name);
        filter.name = searchFilter.value.name;
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
            <UDashboardNavbar :title="trans('crm.forms.index.title')">
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
                        {{ trans('crm.forms.index.create') }}
                    </UButton>
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <div class="flex flex-col gap-8">
                <div
                    class="flex flex-wrap items-center justify-between gap-8 px-2 py-4"
                >
                    <div class="flex flex-1 items-center gap-4">
                        <UInput
                            class="max-w-48"
                            :placeholder="trans('crm.forms.fields.name')"
                            v-model="searchFilter.name"
                        />
                    </div>
                </div>

                <UTable :columns="columns" :data="forms.data">
                    <template #slug-cell="{ row }">
                        <code class="text-muted text-sm">{{
                            row.original.slug
                        }}</code>
                    </template>

                    <template #actions-cell="{ row }">
                        <RowActions
                            v-if="row.original.actions"
                            :actions="row.original.actions"
                        />
                    </template>
                </UTable>

                <template v-if="forms.meta.last_page > 1">
                    <div class="mt-4 flex justify-end">
                        <UPagination
                            :items-per-page="forms.meta.per_page"
                            :page="forms.meta.current_page"
                            :total="forms.meta.total"
                            @update:page="(page: any) => search(page)"
                        />
                    </div>
                </template>
            </div>
        </template>
    </UDashboardPanel>
</template>
