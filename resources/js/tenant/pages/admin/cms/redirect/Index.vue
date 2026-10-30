<script setup lang="ts">
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Cms/RedirectController';
import RowActions from '@/components/RowActions.vue';
import { formatDateTime, userTimezone } from '@/lib/timezone';
import type { RedirectData, RedirectStatus, SelectItem } from '@/types';

const props = defineProps<{
    filter?: {
        search?: string;
        status?: string;
    };
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    redirects: {
        data: RedirectData[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
    statuses: SelectItem[];
    canCreate: boolean;
}>();

const { trans } = useTrans();
const timezone = userTimezone(useAuth().value.user);

const statusColors: Record<RedirectStatus, 'success' | 'info' | 'neutral'> = {
    active: 'success',
    scheduled: 'info',
    expired: 'neutral',
    disabled: 'neutral',
};

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => index.url(),
    initial: {
        search: props.filter?.search || '',
        status: props.filter?.status || '',
    },
    labels: {
        status: {
            label: trans('cms.redirects.filters.status'),
            items: () => props.statuses,
        },
    },
});
</script>

<template>
    <ListLayout
        v-model:search="filters.search"
        :title="trans('cms.redirects.index.title')"
        :columns="columns"
        link-column="url_from"
        :rows="redirects.data"
        :pagination="redirects.meta"
        :search-placeholder="trans('cms.redirects.filters.search')"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('cms.redirects.index.empty')"
        :create-label="
            canCreate ? trans('cms.redirects.index.create') : undefined
        "
        :create-href="canCreate ? create.url() : undefined"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template #filters>
            <USelect
                class="w-full max-w-40"
                :items="statuses"
                :placeholder="trans('cms.redirects.filters.status')"
                v-model="filters.status"
            />
        </template>

        <template #url_from-cell="{ row }">
            <ListRecordLink :actions="row.original.actions">
                <span class="font-mono">{{ row.original.url_from }}</span>
            </ListRecordLink>
        </template>

        <template #url_to-cell="{ row }">
            <span class="flex items-center gap-1 font-mono">
                {{ row.original.url_to }}
                <UIcon
                    v-if="row.original.is_external"
                    name="i-lucide-external-link"
                    class="text-muted size-3.5"
                    :aria-label="trans('cms.redirects.external')"
                />
            </span>
        </template>

        <template #type-cell="{ row }">
            {{
                row.original.is_permanent
                    ? trans('cms.redirects.types.permanent')
                    : trans('cms.redirects.types.temporary')
            }}
        </template>

        <template #status-cell="{ row }">
            <div class="flex flex-col items-start gap-0.5">
                <UBadge
                    :color="statusColors[row.original.status as RedirectStatus]"
                    variant="subtle"
                >
                    {{ row.original.status_label }}
                </UBadge>
                <span
                    v-if="
                        row.original.status === 'scheduled' &&
                        row.original.published_at
                    "
                    class="text-muted text-xs"
                >
                    {{
                        trans('cms.redirects.starts', {
                            date: formatDateTime(
                                row.original.published_at,
                                timezone,
                            ),
                        })
                    }}
                </span>
                <span
                    v-else-if="
                        row.original.status === 'expired' &&
                        row.original.expired_at
                    "
                    class="text-muted text-xs"
                >
                    {{
                        trans('cms.redirects.ended', {
                            date: formatDateTime(
                                row.original.expired_at,
                                timezone,
                            ),
                        })
                    }}
                </span>
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
