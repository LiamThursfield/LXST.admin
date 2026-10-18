<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Cms/PageController';
import RowActions from '@/components/RowActions.vue';
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

const searchFilter = ref({
    search: props.filter?.search || '',
    status: props.filter?.status || '',
});

const search = useDebounceFn((page: number = 1) => {
    const query = new URLSearchParams();
    const filter: Record<string, string> = {};

    const newValue = searchFilter.value;

    if (newValue.search.length) {
        query.append('filter[search]', newValue.search);
        filter.search = newValue.search;
    }

    if (newValue.status.length) {
        query.append('filter[status]', newValue.status);
        filter.status = newValue.status;
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
            <UDashboardNavbar :title="trans('cms.pages.index.title')">
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
                        {{ trans('cms.pages.index.create') }}
                    </UButton>
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('cms.pages.index.title')" />
            <div class="flex flex-col gap-8">
                <div
                    class="flex flex-wrap items-center justify-between gap-8 px-2 py-4"
                >
                    <div class="flex flex-1 items-center gap-4">
                        <UInput
                            class="max-w-64"
                            icon="i-lucide-search"
                            :placeholder="trans('cms.pages.filters.search')"
                            v-model="searchFilter.search"
                        />
                        <USelect
                            class="w-full max-w-40"
                            :items="statuses"
                            :placeholder="trans('cms.pages.filters.status')"
                            v-model="searchFilter.status"
                        />
                    </div>
                </div>

                <UTable
                    :columns="columns"
                    :data="pages.data"
                    :empty="trans('cms.pages.index.empty')"
                >
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
                            {{ row.original.name }}
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
                                :color="
                                    statusColors[
                                        row.original.status as PublishStatus
                                    ]
                                "
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
                </UTable>

                <template v-if="pages.meta.last_page > 1">
                    <div class="mt-4 flex justify-end">
                        <UPagination
                            :items-per-page="pages.meta.per_page"
                            :page="pages.meta.current_page"
                            :total="pages.meta.total"
                            @update:page="(page: any) => search(page)"
                        />
                    </div>
                </template>
            </div>
        </template>
    </UDashboardPanel>
</template>
