<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { create } from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/ServiceSessionController';
import RowActions from '@/components/RowActions.vue';
import type { ServiceSessionData } from '@/types';

defineProps<{
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    sessions: {
        data: ServiceSessionData[];
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

function goToPage(page: number) {
    router.get(window.location.pathname, { page }, { preserveScroll: true });
}
</script>

<template>
    <ListLayout
        :title="trans('bookings.sessions.index.title')"
        :columns="columns"
        :rows="sessions.data"
        :pagination="sessions.meta"
        :empty-title="trans('bookings.sessions.index.empty')"
        :create-label="
            canCreate ? trans('bookings.sessions.index.create') : undefined
        "
        :create-href="canCreate ? create.url() : undefined"
        @page="goToPage"
    >
        <template #actions-cell="{ row }">
            <RowActions
                v-if="row.original.actions"
                :actions="row.original.actions"
            />
        </template>
    </ListLayout>
</template>
