<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/UserController';
import RowActions from '@/components/RowActions.vue';

const props = defineProps<{
    filter?: {
        first_name?: string;
        last_name?: string;
        email?: string;
        role?: string[];
    };
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    users: {
        data: Array<{
            id: number;
            first_name: string;
            last_name: string;
            email: string;
            roles?: string[];
            actions?: any[];
        }>;
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
    roles: Array<{
        label: string;
        value: string;
    }>;
}>();

const searchFilter = ref({
    first_name: props.filter?.first_name || '',
    last_name: props.filter?.last_name || '',
    email: props.filter?.email || '',
    role: props.filter?.role || [],
});

const search = useDebounceFn((page: number = 1) => {
    console.log('searching page:', page, searchFilter.value);
    const query = new URLSearchParams();
    const filter = {};

    const newValue = searchFilter.value;

    if (newValue.first_name.length) {
        query.append('filter[first_name]', newValue.first_name);
    }

    if (newValue.last_name.length) {
        query.append('filter[last_name]', newValue.last_name);
    }

    if (newValue.email.length) {
        query.append('filter[email]', newValue.email);
    }

    if (newValue.role.length) {
        // add a filter for each role
        newValue.role.forEach((role) => {
            query.append('filter[role][]', role);
        });
    }

    let url = `${window.location.pathname}`;
    if (query.size > 0) {
        url += `?${query.toString()}`;
    }
    console.log(url);
    window.history.pushState({}, '', url);

    for (const key in searchFilter.value) {
        if (searchFilter.value[key].length) {
            filter[key] = searchFilter.value[key];
        }
    }

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
            <UDashboardNavbar title="Settings">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>

                <template #right>
                    <UButton :as="Link" :href="create.url()" color="primary">
                        <UIcon name="i-lucide-plus" />
                        New user
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
                            placeholder="First Name"
                            v-model="searchFilter.first_name"
                        />
                        <UInput
                            class="max-w-48"
                            placeholder="Last Name"
                            v-model="searchFilter.last_name"
                        />

                        <UInput
                            class="max-w-48"
                            placeholder="Email"
                            v-model="searchFilter.email"
                        />

                        <USelect
                            class="w-full max-w-32"
                            :items="roles"
                            multiple
                            placeholder="Role"
                            v-model="searchFilter.role"
                        />
                    </div>
                </div>

                <UTable :columns="columns" :data="users.data">
                    <template #roles-cell="{ row }">
                        <div
                            v-if="
                                row.original.roles && row.original.roles.length
                            "
                            class="flex gap-2"
                        >
                            <UBadge
                                v-for="role in row.original.roles"
                                :key="role"
                                color="primary"
                                variant="subtle"
                            >
                                {{ role }}
                            </UBadge>
                        </div>
                        <span v-else class="text-gray-400">-</span>
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
                            :model-value="users.meta.current_page"
                            :total="users.meta.total"
                            @update:page="(page: any) => search(page)"
                        />
                    </div>
                </template>
            </div>
        </template>
    </UDashboardPanel>
</template>
