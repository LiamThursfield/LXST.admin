<script setup lang="ts">
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

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => index.url(),
    initial: {
        search: props.filter?.search || '',
    },
});
</script>

<template>
    <ListLayout
        v-model:search="filters.search"
        :title="trans('users.index.title')"
        :columns="columns"
        link-column="first_name"
        :rows="users.data"
        :pagination="users.meta"
        :search-placeholder="trans('users.central.search')"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('users.index.empty')"
        :create-label="trans('users.index.create')"
        :create-href="create.url()"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
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
    </ListLayout>
</template>
