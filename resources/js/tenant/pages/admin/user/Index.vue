<script setup lang="ts">
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/UserController';
import RowActions from '@/components/RowActions.vue';

const { trans } = useTrans();

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

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => index.url(),
    initial: {
        first_name: props.filter?.first_name || '',
        last_name: props.filter?.last_name || '',
        email: props.filter?.email || '',
        role: props.filter?.role || [],
    },
    labels: {
        role: {
            label: trans('users.fields.role'),
            items: () => props.roles,
        },
    },
});
</script>

<template>
    <ListLayout
        v-model:search="filters.first_name"
        :title="trans('users.index.title')"
        :columns="columns"
        link-column="first_name"
        :rows="users.data"
        :pagination="users.meta"
        :search-placeholder="trans('users.fields.first_name')"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('users.index.empty')"
        :create-label="trans('users.index.create')"
        :create-href="create.url()"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template #filters>
            <UInput
                class="w-full sm:w-48"
                :placeholder="trans('users.fields.last_name')"
                v-model="filters.last_name"
            />
            <UInput
                class="w-full sm:w-48"
                :placeholder="trans('users.fields.email')"
                v-model="filters.email"
            />
            <USelect
                class="w-full sm:w-32"
                :items="roles"
                multiple
                :placeholder="trans('users.fields.role')"
                v-model="filters.role"
            />
        </template>

        <template #roles-cell="{ row }">
            <div
                v-if="row.original.roles && row.original.roles.length"
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
    </ListLayout>
</template>
