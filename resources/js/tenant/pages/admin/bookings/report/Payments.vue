<script setup lang="ts">
import { Deferred, Head, router } from '@inertiajs/vue3';
import PaymentReportController from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/PaymentReportController';

type StripeSummary = {
    connected: boolean;
    error: string | null;
    available: string[];
    pending: string[];
    payouts: Array<{
        id: string;
        amount: string;
        status: string;
        arrives: string;
    }>;
};

const props = defineProps<{
    months: number;
    periods: Array<{ value: number; label: string }>;
    columns: Array<{ key: string; label: string }>;
    rows: Array<Record<string, string>>;
    totals: Record<string, string>;
    // Deferred, as it's fetched from Stripe once the page has shown
    stripe?: StripeSummary;
}>();

const { trans } = useTrans();

const period = ref(props.months);

watch(period, (months) => {
    router.get(
        PaymentReportController.url(),
        { months },
        { preserveScroll: true, preserveState: true },
    );
});
</script>

<template>
    <UDashboardPanel id="payment-report">
        <template #header>
            <UDashboardNavbar :title="trans('bookings.reports.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
                <template #right>
                    <USelect
                        v-model="period"
                        :items="periods"
                        class="w-44"
                        :aria-label="trans('bookings.reports.month')"
                    />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('bookings.reports.title')" />

            <p class="text-muted max-w-2xl text-sm">
                {{ trans('bookings.reports.description') }}
            </p>

            <UPageCard variant="subtle">
                <div class="overflow-x-auto">
                    <table class="min-w-3xl w-full text-sm">
                        <thead>
                            <tr class="border-default border-b text-left">
                                <th class="py-2 pr-4 font-medium">
                                    {{ trans('bookings.reports.month') }}
                                </th>
                                <th
                                    v-for="column in columns"
                                    :key="column.key"
                                    class="py-2 pr-4 text-right font-medium"
                                >
                                    {{ column.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in rows"
                                :key="row.month"
                                class="border-default border-b"
                            >
                                <td class="py-2 pr-4">{{ row.label }}</td>
                                <td
                                    v-for="column in columns"
                                    :key="column.key"
                                    class="py-2 pr-4 text-right"
                                    :class="{
                                        'font-medium': column.key === 'net',
                                    }"
                                >
                                    {{ row[column.key] }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="font-semibold">
                                <td class="py-2 pr-4">
                                    {{ trans('bookings.reports.total') }}
                                </td>
                                <td
                                    v-for="column in columns"
                                    :key="column.key"
                                    class="py-2 pr-4 text-right"
                                >
                                    {{ totals[column.key] }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </UPageCard>

            <UPageCard
                variant="subtle"
                :title="trans('bookings.reports.stripe')"
                :description="trans('bookings.reports.stripe_help')"
            >
                <Deferred data="stripe">
                    <template #fallback>
                        <div class="flex flex-col gap-2">
                            <USkeleton class="h-5 w-48" />
                            <USkeleton class="h-5 w-64" />
                            <USkeleton class="h-5 w-56" />
                        </div>
                    </template>

                    <p
                        v-if="stripe && !stripe.connected"
                        class="text-muted text-sm"
                    >
                        {{ trans('bookings.reports.not_connected') }}
                    </p>
                    <p v-else-if="stripe?.error" class="text-error text-sm">
                        {{ stripe.error }}
                    </p>
                    <div v-else-if="stripe" class="flex flex-col gap-4 text-sm">
                        <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <dt class="font-medium">
                                    {{ trans('bookings.reports.available') }}
                                </dt>
                                <dd class="text-muted">
                                    {{ stripe.available.join(', ') || '–' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="font-medium">
                                    {{ trans('bookings.reports.pending') }}
                                </dt>
                                <dd class="text-muted">
                                    {{ stripe.pending.join(', ') || '–' }}
                                </dd>
                            </div>
                        </dl>

                        <div class="flex flex-col gap-2">
                            <p class="font-medium">
                                {{ trans('bookings.reports.payouts') }}
                            </p>
                            <p v-if="!stripe.payouts.length" class="text-muted">
                                {{ trans('bookings.reports.no_payouts') }}
                            </p>
                            <ul v-else class="flex flex-col gap-1">
                                <li
                                    v-for="payout in stripe.payouts"
                                    :key="payout.id"
                                    class="flex flex-wrap gap-2"
                                >
                                    <span class="font-medium">{{
                                        payout.amount
                                    }}</span>
                                    <span class="text-muted">
                                        {{ payout.status }} ·
                                        {{
                                            trans('bookings.reports.arrives', {
                                                date: payout.arrives,
                                            })
                                        }}
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </Deferred>
            </UPageCard>
        </template>
    </UDashboardPanel>
</template>
