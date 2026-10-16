<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Tracker/TaskDefinitionController';
import RowActions from '@/components/RowActions.vue';

const { trans } = useTrans();

const props = defineProps<{
    filter?: {
        title?: string;
        status?: string;
    };
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    tasks: {
        data: Array<{
            id: number;
            title: string;
            period: string;
            status: string;
            actions?: any[];
        }>;
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
    statuses: Array<{ label: string; value: string }>;
}>();

const searchFilter = ref({
    title: props.filter?.title || '',
    status: props.filter?.status || '',
});

const search = useDebounceFn((page: number = 1) => {
    const query = new URLSearchParams();
    const filter: Record<string, string> = {};

    const newValue = searchFilter.value;

    if (newValue.title.length) {
        query.append('filter[title]', newValue.title);
        filter.title = newValue.title;
    }

    if (newValue.status.length) {
        query.append('filter[status]', newValue.status);
        filter.status = newValue.status;
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
            <UDashboardNavbar :title="trans('tracker.tasks.index.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>

                <template #right>
                    <UButton :as="Link" :href="create.url()" color="primary">
                        <UIcon name="i-lucide-plus" />
                        {{ trans('tracker.tasks.index.create') }}
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
                            :placeholder="trans('tracker.tasks.fields.title')"
                            v-model="searchFilter.title"
                        />
                        <USelect
                            class="w-full max-w-40"
                            :items="statuses"
                            :placeholder="trans('tracker.tasks.fields.status')"
                            v-model="searchFilter.status"
                        />
                    </div>
                </div>

                <UTable :columns="columns" :data="tasks.data">
                    <template #status-cell="{ row }">
                        <UBadge color="neutral" variant="subtle">
                            {{ row.original.status }}
                        </UBadge>
                    </template>

                    <template #actions-cell="{ row }">
                        <RowActions
                            v-if="row.original.actions"
                            :actions="row.original.actions"
                        />
                    </template>
                </UTable>

                <template v-if="tasks.meta.last_page > 1">
                    <div class="mt-4 flex justify-end">
                        <UPagination
                            :items-per-page="tasks.meta.per_page"
                            :page="tasks.meta.current_page"
                            :total="tasks.meta.total"
                            @update:page="(page: any) => search(page)"
                        />
                    </div>
                </template>
            </div>
        </template>
    </UDashboardPanel>
</template>
