<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Tracker/CategoryController';
import RowActions from '@/components/RowActions.vue';

const { trans } = useTrans();

const props = defineProps<{
    filter?: {
        name?: string;
    };
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    categories: {
        data: Array<{
            id: number;
            name: string;
            colour?: string | null;
            icon?: string | null;
            is_public: boolean;
            user_id?: number | null;
            actions?: any[];
        }>;
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
    canManagePublic: boolean;
}>();

const searchFilter = ref({
    name: props.filter?.name || '',
});

const search = useDebounceFn((page: number = 1) => {
    const query = new URLSearchParams();
    const filter: Record<string, string> = {};

    const newValue = searchFilter.value;

    if (newValue.name.length) {
        query.append('filter[name]', newValue.name);
        filter.name = newValue.name;
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
            <UDashboardNavbar :title="trans('tracker.categories.index.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>

                <template #right>
                    <UButton :as="Link" :href="create.url()" color="primary">
                        <UIcon name="i-lucide-plus" />
                        {{ trans('tracker.categories.index.create') }}
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
                                trans('tracker.categories.fields.name')
                            "
                            v-model="searchFilter.name"
                        />
                    </div>
                </div>

                <UTable :columns="columns" :data="categories.data">
                    <template #name-cell="{ row }">
                        <div class="flex items-center gap-2">
                            <UIcon
                                v-if="row.original.icon"
                                :name="row.original.icon"
                            />
                            <span>{{ row.original.name }}</span>
                        </div>
                    </template>

                    <template #is_public-cell="{ row }">
                        <UBadge
                            :color="
                                row.original.is_public ? 'primary' : 'neutral'
                            "
                            variant="subtle"
                        >
                            {{
                                row.original.is_public
                                    ? trans(
                                          'tracker.categories.visibility.public',
                                      )
                                    : trans(
                                          'tracker.categories.visibility.private',
                                      )
                            }}
                        </UBadge>
                    </template>

                    <template #actions-cell="{ row }">
                        <RowActions
                            v-if="row.original.actions"
                            :actions="row.original.actions"
                        />
                    </template>
                </UTable>

                <template v-if="categories.meta.last_page > 1">
                    <div class="mt-4 flex justify-end">
                        <UPagination
                            :items-per-page="categories.meta.per_page"
                            :page="categories.meta.current_page"
                            :total="categories.meta.total"
                            @update:page="(page: any) => search(page)"
                        />
                    </div>
                </template>
            </div>
        </template>
    </UDashboardPanel>
</template>
