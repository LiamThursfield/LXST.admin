<script setup lang="ts">
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Crm/ContactController';
import RowActions from '@/components/RowActions.vue';
import type { ContactData } from '@/types';

const props = defineProps<{
    filter?: {
        search?: string;
        is_spam?: string;
        trashed?: string;
    };
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    contacts: {
        data: ContactData[];
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

/** The spam filter, where 'any' means no filter. */
const spamItems = [
    { label: trans('crm.contacts.filters.any'), value: 'any' },
    { label: trans('crm.contacts.filters.genuine_only'), value: '0' },
    { label: trans('crm.contacts.filters.spam_only'), value: '1' },
];

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => index.url(),
    initial: {
        search: props.filter?.search ?? '',
        is_spam: props.filter?.is_spam ?? 'any',
        trashed: props.filter?.trashed === 'only' ? 'only' : '',
    },
    blanks: { is_spam: 'any' },
    labels: {
        is_spam: {
            label: trans('crm.contacts.filters.spam'),
            items: spamItems,
        },
        trashed: {
            label: trans('crm.contacts.filters.show_deleted'),
            format: () => trans('crm.contacts.filters.show_deleted'),
        },
    },
});

/** The "show deleted" switch, sent as `trashed=only`. */
const showDeleted = computed({
    get: () => filters.value.trashed === 'only',
    set: (show: boolean) => {
        filters.value.trashed = show ? 'only' : '';
    },
});
</script>

<template>
    <ListLayout
        v-model:search="filters.search"
        :title="trans('crm.contacts.index.title')"
        :columns="columns"
        link-column="email"
        link-to-record-page
        :rows="contacts.data"
        :pagination="contacts.meta"
        :search-placeholder="trans('crm.contacts.filters.search')"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('crm.contacts.index.empty')"
        :create-label="
            canCreate ? trans('crm.contacts.index.create') : undefined
        "
        :create-href="canCreate ? create.url() : undefined"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template #filters>
            <USelect
                v-model="filters.is_spam"
                class="w-full sm:w-40"
                :items="spamItems"
                :aria-label="trans('crm.contacts.filters.spam')"
            />
            <ListFilterToggle
                v-model="showDeleted"
                :label="trans('crm.contacts.filters.show_deleted')"
            />
        </template>

        <template #name_with_title-cell="{ row }">
            <span v-if="row.original.name_with_title">{{
                row.original.name_with_title
            }}</span>
            <span v-else class="text-gray-400">-</span>
        </template>

        <template #telephone-cell="{ row }">
            <span v-if="row.original.telephone">{{
                row.original.telephone
            }}</span>
            <span v-else class="text-gray-400">-</span>
        </template>

        <template #is_spam-cell="{ row }">
            <UBadge
                v-if="row.original.deleted_at"
                color="neutral"
                variant="outline"
            >
                {{ trans('crm.contacts.status.deleted') }}
            </UBadge>
            <UBadge
                v-else-if="row.original.is_spam"
                color="error"
                variant="subtle"
            >
                {{ trans('crm.contacts.status.spam') }}
            </UBadge>
            <UBadge v-else color="success" variant="subtle">
                {{ trans('crm.contacts.status.genuine') }}
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
