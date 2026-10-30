<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
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

/** The gigs to filter by, keyed by string ids, as the filters are kept in the URL. */
const gigItems = computed(() =>
    props.gigs.map((gig) => ({ ...gig, value: String(gig.value) })),
);

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => index.url(),
    initial: {
        search: props.filter.search ?? '',
        gig_id: props.filter.gig_id ? String(props.filter.gig_id) : '',
    },
    labels: {
        gig_id: {
            label: trans('dj.setlists.fields.gig'),
            items: gigItems,
        },
    },
});

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
    <ListLayout
        v-model:search="filters.search"
        :title="trans('dj.setlists.title')"
        :columns="columns"
        :rows="setlists.data"
        :pagination="setlists.meta"
        :search-placeholder="trans('dj.setlists.search')"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('dj.setlists.empty')"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template #actions>
            <UButton
                v-if="canCreate"
                icon="i-lucide-plus"
                :label="trans('dj.setlists.create')"
                @click="openCreate"
            />
        </template>

        <template #notice>
            <p class="text-muted text-sm">
                {{ trans('dj.setlists.description') }}
            </p>
        </template>

        <template #filters>
            <USelectMenu
                v-model="filters.gig_id"
                :items="gigItems"
                value-key="value"
                class="w-full sm:w-64"
                :placeholder="trans('dj.setlists.fields.gig')"
            />
        </template>

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

        <UModal v-model:open="createOpen" :title="trans('dj.setlists.create')">
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
                        <UInput v-model="form.name" class="w-full" autofocus />
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
    </ListLayout>
</template>
