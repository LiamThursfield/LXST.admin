<script setup lang="ts">
import {
    capitalise,
    demoBusinesses,
    statusColours,
    useIndustryTerms,
    type DemoIndustry,
} from '@/lib/marketing';
import MockWindow from './MockWindow.vue';

const props = withDefaults(defineProps<{ industry?: DemoIndustry }>(), {
    industry: 'beauty',
});

const { trans } = useTrans();
const term = useIndustryTerms();

const business = computed(() => demoBusinesses[props.industry]);
const words = computed(() => ({
    bookings: capitalise(term(props.industry, 'booking')),
    customers: capitalise(term(props.industry, 'customer')),
    services: capitalise(term(props.industry, 'service')),
    staff: capitalise(term(props.industry, 'staff_member')),
    resources: capitalise(term(props.industry, 'resource')),
}));

const nav = computed(() => [
    { label: trans('marketing.mock.dashboard'), icon: 'i-lucide-house' },
    {
        label: words.value.bookings,
        icon: 'i-lucide-calendar-check',
        active: true,
    },
    { label: words.value.customers, icon: 'i-lucide-users' },
    { label: words.value.services, icon: 'i-lucide-tag' },
    { label: words.value.staff, icon: 'i-lucide-id-card' },
    { label: words.value.resources, icon: 'i-lucide-armchair' },
    { label: trans('marketing.mock.website'), icon: 'i-lucide-globe' },
]);

const stats = computed(() => [
    {
        label: `${words.value.bookings}, ${trans('marketing.mock.today').toLowerCase()}`,
        value: String(business.value.count),
    },
    {
        label: trans('marketing.mock.paid_online'),
        value: business.value.paidOnline,
    },
    {
        label: `${words.value.customers}, ${trans('marketing.mock.new_this_week').toLowerCase()}`,
        value: String(business.value.newCustomers),
    },
]);
</script>

<template>
    <MockWindow
        :business="business.name"
        :domain="business.domain"
        :title="`${words.bookings} · ${trans('marketing.mock.today')}`"
        :nav="nav"
    >
        <template #actions>
            <span
                class="bg-primary text-inverted rounded-md px-2.5 py-1 text-xs font-medium"
            >
                + {{ term(industry, 'booking', false) }}
            </span>
        </template>

        <dl class="grid grid-cols-3 gap-3">
            <div
                v-for="stat in stats"
                :key="stat.label"
                class="border-default rounded-lg border px-3 py-2.5"
            >
                <dt class="text-muted text-xs leading-tight">
                    {{ stat.label }}
                </dt>
                <dd
                    class="text-highlighted mt-1 text-lg font-semibold tabular-nums"
                >
                    {{ stat.value }}
                </dd>
            </div>
        </dl>

        <ul
            class="divide-default border-default mt-4 divide-y rounded-lg border"
        >
            <li
                v-for="booking in business.bookings"
                :key="booking.time"
                class="flex items-center gap-3 px-3 py-2.5 text-sm"
            >
                <span class="text-muted w-11 shrink-0 tabular-nums">
                    {{ booking.time }}
                </span>
                <span class="min-w-0 flex-1">
                    <span class="text-highlighted block truncate font-medium">
                        {{ booking.title }}
                    </span>
                    <span class="text-muted block truncate text-xs">
                        {{ booking.detail }}
                    </span>
                </span>
                <UBadge
                    :color="statusColours[booking.status]"
                    variant="subtle"
                    size="sm"
                    class="shrink-0"
                    :label="trans(`marketing.mock.status.${booking.status}`)"
                />
            </li>
        </ul>
    </MockWindow>
</template>
