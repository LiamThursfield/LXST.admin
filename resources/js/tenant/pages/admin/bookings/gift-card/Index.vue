<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/GiftCardController';
import type { GiftCardRow, SelectItem } from '@/types';

const props = defineProps<{
    filter?: { status?: string; kind?: string; search?: string };
    columns: Array<{ accessorKey: string; header: string }>;
    giftCards: {
        data: GiftCardRow[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
    statuses: SelectItem[];
    kinds: SelectItem[];
    canCreate: boolean;
}>();

const { trans } = useTrans();

/** 'any' means no filter for the select inputs. */
const statusItems = computed(() => [
    { label: trans('bookings.gift_cards.all_statuses'), value: 'any' },
    ...props.statuses,
]);

const kindItems = computed(() => [
    { label: trans('bookings.gift_cards.all_kinds'), value: 'any' },
    ...props.kinds,
]);

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => index.url(),
    initial: {
        search: props.filter?.search ?? '',
        status: props.filter?.status ?? 'any',
        kind: props.filter?.kind ?? 'any',
    },
    blanks: { status: 'any', kind: 'any' },
    labels: {
        status: {
            label: trans('bookings.gift_cards.columns.status'),
            items: () => props.statuses,
        },
        kind: {
            label: trans('bookings.gift_cards.fields.kind'),
            items: () => props.kinds,
        },
    },
});

const statusColours: Record<string, 'success' | 'warning' | 'neutral'> = {
    active: 'success',
    pending: 'warning',
};
</script>

<template>
    <ListLayout
        v-model:search="filters.search"
        :title="trans('bookings.gift_cards.title')"
        :columns="columns"
        :rows="giftCards.data"
        :pagination="giftCards.meta"
        :search-placeholder="trans('bookings.gift_cards.search')"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('bookings.gift_cards.empty')"
        :create-label="
            canCreate ? trans('bookings.gift_cards.create') : undefined
        "
        :create-href="canCreate ? create.url() : undefined"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template #filters>
            <USelect
                v-model="filters.kind"
                class="w-full sm:w-48"
                :items="kindItems"
                :aria-label="trans('bookings.gift_cards.fields.kind')"
            />
            <USelect
                v-model="filters.status"
                class="w-full sm:w-48"
                :items="statusItems"
                :aria-label="trans('bookings.gift_cards.columns.status')"
            />
        </template>

        <template #code-cell="{ row }">
            <Link :href="row.original.show_url" class="text-primary font-mono">
                {{ row.original.code }}
            </Link>
        </template>

        <template #balance-cell="{ row }">
            {{ row.original.balance ?? '–' }}
        </template>

        <template #recipient-cell="{ row }">
            {{ row.original.recipient ?? '–' }}
        </template>

        <template #status_label-cell="{ row }">
            <UBadge
                :color="statusColours[row.original.status] ?? 'neutral'"
                variant="subtle"
                :label="row.original.status_label"
            />
        </template>
    </ListLayout>
</template>
