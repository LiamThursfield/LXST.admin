<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    index,
    store,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/SetlistController';
import RowActions from '@/components/RowActions.vue';
import { formatDateTime } from '@/lib/timezone';
import type { SelectItem, SetlistData } from '@/types';

const props = defineProps<{
    filter: { search?: string; gig_id?: number | string };
    setlists: {
        data: SetlistData[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
    gigs: SelectItem[];
    visibilities: SelectItem[];
    canCreate: boolean;
}>();

const { trans } = useTrans();

const filters = ref({
    search: props.filter.search ?? '',
    gig_id: props.filter.gig_id ? Number(props.filter.gig_id) : undefined,
});

const search = useDebounceFn((page: number = 1) => {
    const filter: Record<string, string | number> = {};

    if (filters.value.search) {
        filter.search = filters.value.search;
    }

    if (filters.value.gig_id) {
        filter.gig_id = filters.value.gig_id;
    }

    router.get(
        index.url(),
        { page, filter },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}, 300);

watch(filters, () => search(), { deep: true });

const createOpen = ref(false);
const form = useForm({
    name: '',
    gig_id: null as number | null,
    visibility: 'private',
    description: '',
});

function openCreate() {
    form.reset();
    form.clearErrors();
    createOpen.value = true;
}

function create() {
    form.submit(store(), { onSuccess: () => (createOpen.value = false) });
}

const columns = [
    { accessorKey: 'name', header: trans('dj.setlists.fields.name') },
    { accessorKey: 'gig', header: trans('dj.setlists.fields.gig') },
    { accessorKey: 'counts', header: trans('dj.setlists.songs') },
    { accessorKey: 'actions', header: '' },
];
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('dj.setlists.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
                <template #right>
                    <UButton
                        v-if="canCreate"
                        icon="i-lucide-plus"
                        :label="trans('dj.setlists.create')"
                        @click="openCreate"
                    />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('dj.setlists.title')" />

            <div class="flex flex-col gap-6">
                <p class="text-muted text-sm">
                    {{ trans('dj.setlists.description') }}
                </p>

                <div class="flex flex-wrap items-center gap-3">
                    <UInput
                        v-model="filters.search"
                        class="w-full sm:w-auto sm:min-w-48 sm:max-w-80 sm:flex-1"
                        icon="i-lucide-search"
                        :placeholder="trans('dj.setlists.search')"
                    />
                    <USelectMenu
                        v-model="filters.gig_id"
                        :items="gigs"
                        value-key="value"
                        class="w-full sm:w-64"
                        :placeholder="trans('dj.setlists.fields.gig')"
                    />
                </div>

                <UTable
                    :columns="columns"
                    :data="setlists.data"
                    :empty="trans('dj.setlists.empty')"
                >
                    <template #name-cell="{ row }">
                        <div class="flex flex-col">
                            <Link
                                :href="row.original.url"
                                class="text-highlighted font-medium hover:underline"
                            >
                                {{ row.original.name }}
                            </Link>
                            <span class="text-muted text-xs">{{
                                row.original.visibility_label
                            }}</span>
                        </div>
                    </template>

                    <template #gig-cell="{ row }">
                        <Link
                            v-if="row.original.gig"
                            :href="row.original.gig.url"
                            class="hover:underline"
                        >
                            <span class="flex flex-col">
                                <span>{{ row.original.gig.name }}</span>
                                <span class="text-muted text-xs">
                                    {{
                                        formatDateTime(
                                            row.original.gig.starts_at,
                                            row.original.gig.timezone,
                                        )
                                    }}
                                </span>
                            </span>
                        </Link>
                        <span v-else class="text-muted">{{
                            trans('dj.setlists.no_gig')
                        }}</span>
                    </template>

                    <template #counts-cell="{ row }">
                        <span class="whitespace-normal text-sm">
                            {{
                                trans('dj.setlists.counts', {
                                    must: row.original.counts.must_play,
                                    maybe: row.original.counts.maybe,
                                    never: row.original.counts.do_not_play,
                                })
                            }}
                        </span>
                    </template>

                    <template #actions-cell="{ row }">
                        <RowActions
                            v-if="row.original.actions"
                            :actions="row.original.actions"
                        />
                    </template>
                </UTable>

                <div
                    v-if="setlists.meta.last_page > 1"
                    class="flex justify-end"
                >
                    <UPagination
                        :items-per-page="setlists.meta.per_page"
                        :page="setlists.meta.current_page"
                        :total="setlists.meta.total"
                        @update:page="(page: number) => search(page)"
                    />
                </div>
            </div>

            <UModal
                v-model:open="createOpen"
                :title="trans('dj.setlists.create')"
            >
                <template #body>
                    <form
                        id="setlist-create"
                        class="flex flex-col gap-4"
                        @submit.prevent="create"
                    >
                        <UFormField
                            :label="trans('dj.setlists.fields.name')"
                            :error="form.errors.name"
                            required
                        >
                            <UInput
                                v-model="form.name"
                                class="w-full"
                                autofocus
                            />
                        </UFormField>
                        <UFormField
                            :label="trans('dj.setlists.fields.gig')"
                            :error="form.errors.gig_id"
                        >
                            <USelectMenu
                                v-model="form.gig_id"
                                :items="[
                                    {
                                        value: null,
                                        label: trans('dj.setlists.no_gig'),
                                    },
                                    ...gigs,
                                ]"
                                value-key="value"
                                class="w-full"
                            />
                        </UFormField>
                        <UFormField
                            :label="trans('dj.setlists.fields.visibility')"
                            :help="trans('dj.setlists.fields.visibility_help')"
                            :error="form.errors.visibility"
                        >
                            <USelect
                                v-model="form.visibility"
                                :items="visibilities"
                                class="w-full"
                            />
                        </UFormField>
                    </form>
                </template>
                <template #footer>
                    <div class="flex w-full justify-end gap-2">
                        <UButton
                            color="neutral"
                            variant="ghost"
                            :label="trans('common.actions.cancel')"
                            @click="createOpen = false"
                        />
                        <UButton
                            type="submit"
                            form="setlist-create"
                            :loading="form.processing"
                            :label="trans('dj.setlists.create')"
                        />
                    </div>
                </template>
            </UModal>
        </template>
    </UDashboardPanel>
</template>
