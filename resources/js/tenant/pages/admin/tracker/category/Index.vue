<script setup lang="ts">
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

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => index.url(),
    initial: {
        name: props.filter?.name || '',
    },
});
</script>

<template>
    <ListLayout
        v-model:search="filters.name"
        :title="trans('tracker.categories.index.title')"
        :columns="columns"
        :rows="categories.data"
        :pagination="categories.meta"
        :search-placeholder="trans('tracker.categories.fields.name')"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('tracker.categories.index.empty')"
        :create-label="trans('tracker.categories.index.create')"
        :create-href="create.url()"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template #name-cell="{ row }">
            <div class="flex items-center gap-2">
                <UIcon v-if="row.original.icon" :name="row.original.icon" />
                <ListRecordLink :actions="row.original.actions">
                    {{ row.original.name }}
                </ListRecordLink>
            </div>
        </template>

        <template #is_public-cell="{ row }">
            <UBadge
                :color="row.original.is_public ? 'primary' : 'neutral'"
                variant="subtle"
            >
                {{
                    row.original.is_public
                        ? trans('tracker.categories.visibility.public')
                        : trans('tracker.categories.visibility.private')
                }}
            </UBadge>
        </template>

        <template #actions-cell="{ row }">
            <RowActions
                v-if="row.original.actions"
                :actions="row.original.actions"
            />
        </template>
    </ListLayout>
</template>
