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

const searchFilter = ref({
    search: props.filter?.search || '',
    is_spam: props.filter?.is_spam ?? 'any',
    showDeleted: props.filter?.trashed === 'only',
});

const search = useDebounceFn((page: number = 1) => {
    const query = new URLSearchParams();
    const filter: Record<string, string> = {};

    const newValue = searchFilter.value;

    if (newValue.search.length) {
        filter.search = newValue.search;
    }

    if (newValue.is_spam !== 'any') {
        filter.is_spam = newValue.is_spam;
    }

    if (newValue.showDeleted) {
        filter.trashed = 'only';
    }

    for (const [key, value] of Object.entries(filter)) {
        query.append(`filter[${key}]`, value);
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
            <UDashboardNavbar :title="trans('crm.contacts.index.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>

                <template #right>
                    <UButton
                        v-if="canCreate"
                        as="Link"
                        :href="create.url()"
                        color="primary"
                    >
                        <UIcon name="i-lucide-plus" />
                        {{ trans('crm.contacts.index.create') }}
                    </UButton>
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <div class="flex flex-col gap-8">
                <div
                    class="flex flex-wrap items-center justify-between gap-8 px-2 py-4"
                >
                    <div class="flex flex-1 flex-wrap items-center gap-4">
                        <UInput
                            class="w-full max-w-64"
                            icon="i-lucide-search"
                            :placeholder="trans('crm.contacts.filters.search')"
                            v-model="searchFilter.search"
                        />
                        <USelect
                            class="w-full max-w-40"
                            :items="spamItems"
                            :aria-label="trans('crm.contacts.filters.spam')"
                            v-model="searchFilter.is_spam"
                        />
                        <USwitch
                            :label="trans('crm.contacts.filters.show_deleted')"
                            v-model="searchFilter.showDeleted"
                        />
                    </div>
                </div>

                <UTable :columns="columns" :data="contacts.data">
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
                </UTable>

                <template v-if="contacts.meta.last_page > 1">
                    <div class="mt-4 flex justify-end">
                        <UPagination
                            :items-per-page="contacts.meta.per_page"
                            :page="contacts.meta.current_page"
                            :total="contacts.meta.total"
                            @update:page="(page: any) => search(page)"
                        />
                    </div>
                </template>
            </div>
        </template>
    </UDashboardPanel>
</template>
