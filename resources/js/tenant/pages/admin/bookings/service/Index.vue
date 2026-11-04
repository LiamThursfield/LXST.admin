<script setup lang="ts">
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/ServiceController';
import RowActions from '@/components/RowActions.vue';
import type { ServiceData } from '@/types';

const props = defineProps<{
    filter?: {
        name?: string;
    };
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    services: {
        data: ServiceData[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
    currency: string;
    canCreate: boolean;
}>();

const { trans } = useTrans();

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => index.url(),
    initial: {
        name: props.filter?.name || '',
    },
});

function priceLabel(service: ServiceData): string {
    if (service.price === null) {
        return trans('bookings.services.price_display.on_quote');
    }

    const price = new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: props.currency,
    }).format(Number(service.price));

    return service.price_type === 'from'
        ? trans('bookings.services.price_display.from', { price })
        : price;
}
</script>

<template>
    <ListLayout
        v-model:search="filters.name"
        :title="trans('bookings.services.index.title')"
        :columns="columns"
        :rows="services.data"
        :pagination="services.meta"
        :search-placeholder="trans('bookings.services.fields.name')"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('bookings.services.index.empty')"
        :create-label="
            canCreate ? trans('bookings.services.index.create') : undefined
        "
        :create-href="canCreate ? create.url() : undefined"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template #price-cell="{ row }">
            {{ priceLabel(row.original) }}
        </template>

        <template #is_bookable_online-cell="{ row }">
            {{
                trans(
                    row.original.is_bookable_online
                        ? 'bookings.services.online.yes'
                        : 'bookings.services.online.no',
                )
            }}
        </template>

        <template #actions-cell="{ row }">
            <RowActions
                v-if="row.original.actions"
                :actions="row.original.actions"
            />
        </template>
    </ListLayout>
</template>
