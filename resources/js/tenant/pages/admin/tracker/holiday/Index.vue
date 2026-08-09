<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Tracker/HolidayController';
import RowActions from '@/components/RowActions.vue';

const props = defineProps<{
    filter?: {
        label?: string;
    };
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    holidays: {
        data: Array<{
            id: number;
            label?: string | null;
            starts_on: string;
            ends_on: string;
            actions?: any[];
        }>;
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
}>();

const searchFilter = ref({
    label: props.filter?.label || '',
});

const search = useDebounceFn((page: number = 1) => {
    const query = new URLSearchParams();
    const filter: Record<string, string> = {};

    const newValue = searchFilter.value;

    if (newValue.label.length) {
        query.append('filter[label]', newValue.label);
        filter.label = newValue.label;
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
            <UDashboardNavbar title="Holidays">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>

                <template #right>
                    <UButton :as="Link" :href="create.url()" color="primary">
                        <UIcon name="i-lucide-plus" />
                        New holiday
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
                            placeholder="Label"
                            v-model="searchFilter.label"
                        />
                    </div>
                </div>

                <UTable :columns="columns" :data="holidays.data">
                    <template #label-cell="{ row }">
                        <span v-if="row.original.label">{{
                            row.original.label
                        }}</span>
                        <span v-else class="text-gray-400">-</span>
                    </template>

                    <template #actions-cell="{ row }">
                        <RowActions
                            v-if="row.original.actions"
                            :actions="row.original.actions"
                        />
                    </template>
                </UTable>

                <template v-if="holidays.meta.last_page > 1">
                    <div class="mt-4 flex justify-end">
                        <UPagination
                            :items-per-page="holidays.meta.per_page"
                            :model-value="holidays.meta.current_page"
                            :total="holidays.meta.total"
                            @update:page="(page: any) => search(page)"
                        />
                    </div>
                </template>
            </div>
        </template>
    </UDashboardPanel>
</template>
