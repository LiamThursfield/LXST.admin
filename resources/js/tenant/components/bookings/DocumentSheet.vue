<script setup lang="ts">
import type { BookingDocumentData } from '@/types';

/**
 * A quote or invoice laid out like a paper one: who it's from and to, its lines and totals, and
 * notes. Used in the admin and on the customer's page, and prints cleanly.
 */
defineProps<{
    document: BookingDocumentData;
    /** E.g. "Quote QUO-0001". */
    heading: string;
    /** Dates and the like under the heading. */
    meta: string[];
}>();

const { trans } = useTrans();
</script>

<template>
    <article
        class="border-default flex flex-col gap-6 rounded-xl border p-6 text-sm print:border-0 print:p-0"
    >
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h2 class="text-highlighted text-lg font-semibold">
                    {{ heading }}
                </h2>
                <p v-for="line in meta" :key="line" class="text-muted">
                    {{ line }}
                </p>
            </div>
            <div class="flex flex-col gap-1 text-right">
                <p class="font-semibold">{{ document.business }}</p>
                <p
                    v-if="document.business_details"
                    class="text-muted whitespace-pre-line"
                >
                    {{ document.business_details }}
                </p>
                <p v-if="document.tax_number" class="text-muted">
                    {{ document.tax_number }}
                </p>
            </div>
        </header>

        <div class="flex flex-col gap-1">
            <p class="text-muted text-xs uppercase">
                {{ trans('bookings.documents.public.to') }}
            </p>
            <p class="font-medium">{{ document.customer_name }}</p>
            <p v-if="document.customer_email" class="text-muted">
                {{ document.customer_email }}
            </p>
            <p class="text-muted">
                {{ document.service_name
                }}<template v-if="document.when">
                    · {{ document.when }}</template
                >
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-md w-full border-collapse">
                <thead>
                    <tr class="border-default border-b text-left">
                        <th class="py-2 pr-3 font-medium">
                            {{ trans('bookings.documents.fields.description') }}
                        </th>
                        <th class="py-2 pr-3 text-right font-medium">
                            {{ trans('bookings.documents.fields.quantity') }}
                        </th>
                        <th class="py-2 pr-3 text-right font-medium">
                            {{ trans('bookings.documents.fields.price') }}
                        </th>
                        <th class="py-2 text-right font-medium">
                            {{ trans('bookings.documents.fields.line_total') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(line, index) in document.lines"
                        :key="index"
                        class="border-default border-b"
                    >
                        <td class="py-2 pr-3">{{ line.description }}</td>
                        <td class="py-2 pr-3 text-right">
                            {{ line.quantity }}
                        </td>
                        <td class="py-2 pr-3 text-right">
                            {{ line.unit_price }}
                        </td>
                        <td class="py-2 text-right">{{ line.total }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <dl class="ml-auto grid w-full max-w-xs grid-cols-2 gap-x-4 gap-y-1">
            <dt>{{ trans('bookings.documents.totals.subtotal') }}</dt>
            <dd class="text-right">{{ document.subtotal }}</dd>
            <template v-if="document.tax_label">
                <dt>{{ document.tax_label }}</dt>
                <dd class="text-right">{{ document.tax }}</dd>
            </template>
            <dt class="font-semibold">
                {{ trans('bookings.documents.totals.total') }}
            </dt>
            <dd class="text-right font-semibold">{{ document.total }}</dd>
            <template v-if="document.paid !== undefined">
                <dt>{{ trans('bookings.documents.totals.paid') }}</dt>
                <dd class="text-right">{{ document.paid }}</dd>
                <dt class="font-semibold">
                    {{ trans('bookings.documents.totals.owed') }}
                </dt>
                <dd class="text-right font-semibold">{{ document.owed }}</dd>
            </template>
        </dl>

        <p v-if="document.notes" class="text-muted whitespace-pre-line">
            {{ document.notes }}
        </p>
    </article>
</template>
