<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Cms/PageController';
import RowActions from '@/components/RowActions.vue';
import { recordUrl } from '@/lib/list';
import { formatDateTime, userTimezone } from '@/lib/timezone';
import type { PageData, PublishStatus, SelectItem } from '@/types';

const props = defineProps<{
    filter?: {
        search?: string;
        status?: string;
    };
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    pages: {
        data: PageData[];
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

const statusColors: Record<PublishStatus, 'success' | 'info' | 'neutral'> = {
    published: 'success',
    scheduled: 'info',
    expired: 'neutral',
    draft: 'neutral',
};

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => index.url(),
    initial: {
        search: props.filter?.search ?? '',
        status: props.filter?.status ?? '',
    },
    labels: {
        status: {
            label: trans('cms.pages.filters.status'),
            items: () => props.statuses,
        },
    },
});
</script>

<template>
    <ListLayout
        v-model:search="filters.search"
        :title="trans('cms.pages.index.title')"
        :columns="columns"
        :rows="pages.data"
        :pagination="pages.meta"
        :search-placeholder="trans('cms.pages.filters.search')"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('cms.pages.index.empty')"
        :create-label="canCreate ? trans('cms.pages.index.create') : undefined"
        :create-href="canCreate ? create.url() : undefined"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template #filters>
            <USelect
                v-model="filters.status"
                class="w-full sm:w-40"
                :items="statuses"
                :placeholder="trans('cms.pages.filters.status')"
            />
        </template>

        <template #name-cell="{ row }">
            <span
                class="flex items-center gap-2"
                :style="{
                    paddingLeft: `${row.original.depth * 1.25}rem`,
                }"
            >
                <UIcon
                    :name="
                        row.original.url === '/'
                            ? 'i-lucide-house'
                            : row.original.depth > 0
                              ? 'i-lucide-corner-down-right'
                              : 'i-lucide-file-text'
                    "
                    class="text-muted size-4 shrink-0"
                />
                <Link
                    v-if="recordUrl(row.original.actions)"
                    :href="recordUrl(row.original.actions)!"
                    class="text-highlighted font-medium hover:underline"
                >
                    {{ row.original.name }}
                </Link>
                <span v-else>{{ row.original.name }}</span>
            </span>
        </template>

        <template #url-cell="{ row }">
            <span class="font-mono">{{ row.original.url }}</span>
        </template>

        <template #template-cell="{ row }">
            {{ row.original.template?.name }}
        </template>

        <template #status-cell="{ row }">
            <div class="flex flex-col items-start gap-0.5">
                <UBadge
                    v-if="row.original.status"
                    :color="statusColors[row.original.status as PublishStatus]"
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
                        trans('cms.pages.starts', {
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
                        trans('cms.pages.ended', {
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
