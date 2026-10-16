<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Crm/OrganisationUnitController';
import RowActions from '@/components/RowActions.vue';
import type { OrganisationUnitData, SelectItem } from '@/types';

const props = defineProps<{
    filter?: {
        name?: string;
        type?: string;
    };
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    organisationUnits: {
        data: OrganisationUnitData[];
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
            <UDashboardNavbar
                :title="trans('crm.organisation_units.index.title')"
            >
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
                        {{ trans('crm.organisation_units.index.create') }}
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
                            :placeholder="
                                trans('crm.organisation_units.filters.name')
                            "
                            v-model="searchFilter.name"
                        />
                        <USelect
                            class="w-full max-w-40"
                            :items="types"
                            :placeholder="
                                trans('crm.organisation_units.filters.type')
                            "
                            v-model="searchFilter.type"
                        />
                    </div>
                </div>

                <UTable :columns="columns" :data="organisationUnits.data">
                    <template #type-cell="{ row }">
                        <UBadge color="neutral" variant="subtle">
                            {{ row.original.type_label }}
                        </UBadge>
                    </template>

                    <template #parent-cell="{ row }">
                        <span v-if="row.original.parent">{{
                            row.original.parent.name
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

                <template v-if="organisationUnits.meta.last_page > 1">
                    <div class="mt-4 flex justify-end">
                        <UPagination
                            :items-per-page="organisationUnits.meta.per_page"
                            :page="organisationUnits.meta.current_page"
                            :total="organisationUnits.meta.total"
                            @update:page="(page: any) => search(page)"
                        />
                    </div>
                </template>
            </div>
        </template>
    </UDashboardPanel>
</template>
