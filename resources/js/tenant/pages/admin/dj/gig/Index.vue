<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    create,
    edit,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/GigController';
import {
    destroy as unpin,
    store as pin,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/GigPinController';
import RowActions from '@/components/RowActions.vue';
import { formatDateTime } from '@/lib/timezone';
import type { GigData } from '@/types';

const props = defineProps<{
    filter: { search?: string; when?: string };
    columns: Array<{ accessorKey: string; header: string }>;
    current: { data: GigData } | null;
    gigs: {
        data: GigData[];
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

const filters = ref({
    search: props.filter.search ?? '',
    when: props.filter.when ?? 'upcoming',
});

const whenItems = ['upcoming', 'past', 'all'].map((value) => ({
    value,
    label: trans(`dj.gigs.when.${value}`),
}));

const search = useDebounceFn((page: number = 1) => {
    const filter: Record<string, string> = { when: filters.value.when };

    if (filters.value.search) {
        filter.search = filters.value.search;
    }

    router.get(
        index.url(),
        { page, filter },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}, 300);

watch(filters, () => search(), { deep: true });

/** When a gig is on, on its own clock */
function when(gig: GigData): string {
    return `${formatDateTime(gig.starts_at, gig.timezone)} – ${formatDateTime(gig.ends_at, gig.timezone)}`;
}

function status(gig: GigData): string {
    if (gig.is_pinned) {
        return trans('dj.gigs.pinned');
    }

    return gig.is_on_now ? trans('dj.gigs.on_now') : trans('dj.gigs.up_next');
}

function togglePin(gig: GigData) {
    router.visit((gig.is_pinned ? unpin : pin).url(gig.id), {
        method: gig.is_pinned ? 'delete' : 'post',
        preserveScroll: true,
    });
}
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('dj.gigs.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>

                <template #right>
                    <UButton
                        v-if="canCreate"
                        :as="Link"
                        :href="create.url()"
                        icon="i-lucide-plus"
                        :label="trans('dj.gigs.create')"
                    />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('dj.gigs.title')" />

            <div class="flex flex-col gap-6">
                <p class="text-muted text-sm">
                    {{ trans('dj.gigs.description') }}
                </p>

                <UCard>
                    <template #header>
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <div class="flex flex-col">
                                <span class="text-highlighted font-semibold">
                                    {{ trans('dj.gigs.current') }}
                                </span>
                                <span class="text-muted text-xs">{{
                                    trans('dj.gigs.current_help')
                                }}</span>
                            </div>
                            <UButton
                                v-if="current?.data.can_update"
                                size="sm"
                                color="neutral"
                                variant="outline"
                                :icon="
                                    current.data.is_pinned
                                        ? 'i-lucide-pin-off'
                                        : 'i-lucide-pin'
                                "
                                :label="
                                    current.data.is_pinned
                                        ? trans('dj.gigs.unpin')
                                        : trans('dj.gigs.pin')
                                "
                                @click="togglePin(current.data)"
                            />
                        </div>
                    </template>

                    <div
                        v-if="current"
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div class="flex flex-col gap-1">
                            <Link
                                :href="edit.url(current.data.id)"
                                class="text-highlighted text-lg font-medium hover:underline"
                            >
                                {{ current.data.name }}
                            </Link>
                            <span class="text-muted text-sm">
                                <template v-if="current.data.venue"
                                    >{{ current.data.venue }} ·
                                </template>
                                {{ when(current.data) }}
                            </span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <UBadge color="primary" variant="subtle">{{
                                status(current.data)
                            }}</UBadge>
                            <UBadge
                                :color="
                                    current.data.requests_open
                                        ? 'success'
                                        : 'neutral'
                                "
                                variant="subtle"
                            >
                                {{
                                    current.data.requests_open
                                        ? trans('dj.gigs.requests_open')
                                        : trans('dj.gigs.requests_closed')
                                }}
                            </UBadge>
                        </div>
                    </div>
                    <p v-else class="text-muted text-sm">
                        {{ trans('dj.gigs.no_current') }}
                    </p>
                </UCard>

                <div class="flex flex-wrap items-center gap-3">
                    <UInput
                        v-model="filters.search"
                        class="w-full sm:w-auto sm:min-w-48 sm:max-w-80 sm:flex-1"
                        icon="i-lucide-search"
                        :placeholder="trans('dj.gigs.search')"
                    />
                    <USelect
                        v-model="filters.when"
                        :items="whenItems"
                        class="w-full sm:w-36"
                    />
                </div>

                <UTable
                    :columns="columns"
                    :data="gigs.data"
                    :empty="trans('dj.gigs.empty')"
                >
                    <template #name-cell="{ row }">
                        <div class="flex flex-col">
                            <span class="flex items-center gap-1">
                                <Link
                                    :href="edit.url(row.original.id)"
                                    class="text-highlighted font-medium hover:underline"
                                >
                                    {{ row.original.name }}
                                </Link>
                                <UBadge
                                    v-if="row.original.is_current"
                                    color="primary"
                                    variant="subtle"
                                    size="sm"
                                >
                                    {{ status(row.original) }}
                                </UBadge>
                            </span>
                            <span class="text-muted text-xs">
                                {{
                                    [row.original.venue, row.original.location]
                                        .filter(Boolean)
                                        .join(', ')
                                }}
                            </span>
                        </div>
                    </template>

                    <template #starts_at-cell="{ row }">
                        <span class="whitespace-normal text-sm">{{
                            when(row.original)
                        }}</span>
                    </template>

                    <template #requests_open-cell="{ row }">
                        <UBadge
                            :color="
                                row.original.requests_open
                                    ? 'success'
                                    : 'neutral'
                            "
                            variant="subtle"
                        >
                            {{
                                row.original.requests_open
                                    ? trans('dj.gigs.requests_open')
                                    : trans('dj.gigs.requests_closed')
                            }}
                        </UBadge>
                    </template>

                    <template #actions-cell="{ row }">
                        <RowActions
                            v-if="row.original.actions"
                            :actions="row.original.actions"
                        />
                    </template>
                </UTable>

                <div v-if="gigs.meta.last_page > 1" class="flex justify-end">
                    <UPagination
                        :items-per-page="gigs.meta.per_page"
                        :page="gigs.meta.current_page"
                        :total="gigs.meta.total"
                        @update:page="(page: number) => search(page)"
                    />
                </div>
            </div>
        </template>
    </UDashboardPanel>
</template>
