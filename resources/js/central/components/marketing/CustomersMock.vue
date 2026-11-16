<script setup lang="ts">
import MockWindow from './MockWindow.vue';

const { trans } = useTrans();

const nav = computed(() => [
    {
        label: trans('industries.term_names.customer'),
        icon: 'i-lucide-users',
        active: true,
    },
    { label: trans('marketing.mock.forms'), icon: 'i-lucide-clipboard-list' },
    { label: trans('marketing.mock.locations'), icon: 'i-lucide-map-pin' },
]);

/** Sample data: a salon client, with the beauty industry's own fields */
const details = [
    {
        label: trans('industries.customer_fields.colour_formula'),
        value: '6N + 7G, 20 vol',
    },
    {
        label: trans('industries.customer_fields.patch_test_date'),
        value: '3 Nov 2026',
    },
    {
        label: trans('industries.customer_fields.allergies'),
        value: 'PPD',
        sensitive: true,
    },
    {
        label: trans('industries.customer_fields.referred_by'),
        value: 'Ama Mensah',
    },
];

const history = [
    { date: '14 Nov', title: 'Full head colour', amount: '£95.00' },
    { date: '26 Sep', title: 'Cut and blow-dry', amount: '£48.00' },
    {
        date: '8 Aug',
        title: 'Contact form: "Do you do bridal trials?"',
        amount: null,
    },
];
</script>

<template>
    <MockWindow
        business="Hollow Oak Hair"
        domain="hollowoakhair.co.uk"
        title="Tom Reid"
        :nav="nav"
    >
        <div class="flex items-center gap-3">
            <span
                class="bg-primary/15 text-primary flex size-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
            >
                TR
            </span>
            <div class="min-w-0 text-sm">
                <p class="text-highlighted truncate font-medium">Tom Reid</p>
                <p class="text-muted truncate text-xs">
                    tom.reid@example.com · 07700 900461
                </p>
            </div>
        </div>

        <dl
            class="border-default mt-4 grid grid-cols-2 gap-x-4 gap-y-3 rounded-lg border p-3 text-sm"
        >
            <div v-for="detail in details" :key="detail.label" class="min-w-0">
                <dt class="text-muted flex items-center gap-1 truncate text-xs">
                    {{ detail.label }}
                    <UIcon
                        v-if="detail.sensitive"
                        name="i-lucide-shield"
                        class="text-warning size-3"
                    />
                </dt>
                <dd class="text-highlighted truncate">{{ detail.value }}</dd>
            </div>
        </dl>

        <p class="text-muted mb-2 mt-4 text-xs font-medium">
            {{ trans('marketing.mock.history') }}
        </p>
        <ul class="divide-default border-default divide-y rounded-lg border">
            <li
                v-for="entry in history"
                :key="entry.date"
                class="flex items-center gap-3 px-3 py-2.5 text-sm"
            >
                <span class="text-muted w-12 shrink-0 text-xs tabular-nums">
                    {{ entry.date }}
                </span>
                <span class="text-highlighted min-w-0 flex-1 truncate">
                    {{ entry.title }}
                </span>
                <span
                    v-if="entry.amount"
                    class="text-muted shrink-0 text-xs tabular-nums"
                >
                    {{ entry.amount }}
                </span>
            </li>
        </ul>
    </MockWindow>
</template>
