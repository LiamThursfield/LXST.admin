<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/Central/Admin/UserController';
import RowActions from '@/components/RowActions.vue';
import type { CentralUserData } from '@/types';

const props = defineProps<{
    filter?: {
        search?: string;
    };
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    users: {
        data: CentralUserData[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
}>();

const { trans } = useTrans();

const searchFilter = ref({
    search: props.filter?.search || '',
});

const search = useDebounceFn((page: number = 1) => {
    const query = new URLSearchParams();
    const filter: Record<string, string> = {};

    if (searchFilter.value.search.length) {
        query.append('filter[search]', searchFilter.value.search);
        filter.search = searchFilter.value.search;
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
            <UDashboardNavbar :title="trans('users.index.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>

                <template #right>
                    <UButton as="Link" :href="create.url()" color="primary">
                        <UIcon name="i-lucide-plus" />
                        {{ trans('users.index.create') }}
                    </UButton>
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('users.index.title')" />
            <div class="flex flex-col gap-8">
                <div class="flex items-center gap-4 px-2 py-4">
                    <UInput
                        class="max-w-72"
                        icon="i-lucide-search"
                        :placeholder="trans('users.central.search')"
                        v-model="searchFilter.search"
                    />
                </div>

                <UTable :columns="columns" :data="users.data">
                    <template #email-cell="{ row }">
                        <div class="flex items-center gap-2">
                            {{ row.original.email }}
                            <UBadge
                                v-if="row.original.is_you"
                                color="neutral"
                                variant="subtle"
                            >
                                {{ trans('users.central.you') }}
                            </UBadge>
                        </div>
                    </template>

                    <template #actions-cell="{ row }">
                        <RowActions
                            v-if="row.original.actions"
                            :actions="row.original.actions"
                        />
                    </template>
                </UTable>

                <template v-if="users.meta.last_page > 1">
                    <div class="mt-4 flex justify-end">
                        <UPagination
                            :items-per-page="users.meta.per_page"
                            :page="users.meta.current_page"
                            :total="users.meta.total"
                            @update:page="(page: number) => search(page)"
                        />
                    </div>
                </template>
            </div>
        </template>
    </UDashboardPanel>
</template>
