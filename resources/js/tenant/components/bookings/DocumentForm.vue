<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@nuxt/ui';
import type { DocumentFormBooking, DocumentFormLine } from '@/types';

/**
 * Writing a quote or an invoice: its lines, with a running total and any tax, notes, and the date it
 * lasts until or is due. Saving can send it to the customer too.
 */
const props = withDefaults(
    defineProps<{
        title: string;
        breadcrumbs: BreadcrumbItem[];
        booking: DocumentFormBooking;
        lines: DocumentFormLine[];
        notes?: string | null;
        /** The date field's name: valid_until for a quote, due_on for an invoice. */
        dateField: 'valid_until' | 'due_on';
        date: string | null;
        taxRate: number | null;
        taxLabel: string;
        currency: string;
        /** Anything else to send, e.g. the quote an invoice comes from. */
        extra?: Record<string, unknown>;
        help: string;
        saveLabel: string;
        sendLabel: string;
        /** Where to send it, as a Wayfinder route. */
        action: { url: string; method: 'post' | 'put' };
    }>(),
    { notes: null, extra: () => ({}) },
);

const { trans } = useTrans();

const blankLine = (): DocumentFormLine => ({
    description: '',
    quantity: '1',
    unit_price: '',
});

const form = useForm({
    lines: props.lines.length
        ? props.lines.map((line) => ({ ...line }))
        : [blankLine()],
    notes: props.notes ?? '',
    date: props.date ?? '',
    send: false,
});

/** Server validation errors, including nested ones such as `lines.0.unit_price`. */
const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

const money = (amount: number) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: props.currency,
    }).format(amount);

function lineTotal(line: DocumentFormLine): number {
    return (
        Math.round(
            Number(line.quantity || 0) * Number(line.unit_price || 0) * 100,
        ) / 100
    );
}

const subtotal = computed(() =>
    form.lines.reduce((sum, line) => sum + lineTotal(line), 0),
);
const tax = computed(() =>
    props.taxRate ? Math.round(subtotal.value * props.taxRate) / 100 : 0,
);

function addLine() {
    form.lines.push(blankLine());
}

function removeLine(index: number) {
    form.lines.splice(index, 1);
}

function submit(send: boolean) {
    form.send = send;
    form.transform((data) => ({
        ...props.extra,
        lines: data.lines,
        notes: data.notes || null,
        [props.dateField]: data.date || null,
        send: data.send,
    })).submit(props.action);
}
</script>

<template>
    <EditorLayout
        :title="title"
        :breadcrumbs="breadcrumbs"
        :is-dirty="form.isDirty"
        :processing="form.processing"
        :submit-label="saveLabel"
        @submit="submit(false)"
    >
        <template #actions>
            <UButton
                icon="i-lucide-send"
                color="neutral"
                variant="subtle"
                :label="sendLabel"
                :loading="form.processing && form.send"
                @click="submit(true)"
            />
        </template>

        <UPageCard variant="subtle" :description="help">
            <p class="text-sm">
                <Link :href="booking.url" class="text-primary">
                    {{
                        trans('bookings.documents.for_booking', {
                            customer: booking.customer_name,
                            service: booking.service_name,
                        })
                    }}
                </Link>
                <span v-if="booking.when" class="text-muted">
                    · {{ booking.when }}
                </span>
            </p>

            <p v-if="errors.lines" class="text-error text-sm">
                {{ errors.lines }}
            </p>

            <div class="flex flex-col gap-4">
                <div
                    v-for="(line, index) in form.lines"
                    :key="index"
                    class="border-default grid grid-cols-2 gap-3 border-b pb-4 md:grid-cols-[1fr_6rem_8rem_6rem_auto] md:items-start"
                >
                    <UFormField
                        class="col-span-2 md:col-span-1"
                        :label="trans('bookings.documents.fields.description')"
                        :error="errors[`lines.${index}.description`]"
                        required
                    >
                        <UInput v-model="line.description" class="w-full" />
                    </UFormField>
                    <UFormField
                        :label="trans('bookings.documents.fields.quantity')"
                        :error="errors[`lines.${index}.quantity`]"
                        required
                    >
                        <UInput
                            v-model="line.quantity"
                            type="number"
                            min="0.01"
                            step="0.01"
                            class="w-full"
                        />
                    </UFormField>
                    <UFormField
                        :label="
                            trans('bookings.documents.fields.unit_price', {
                                currency,
                            })
                        "
                        :error="errors[`lines.${index}.unit_price`]"
                        required
                    >
                        <UInput
                            v-model="line.unit_price"
                            type="number"
                            step="0.01"
                            class="w-full"
                        />
                    </UFormField>
                    <UFormField
                        :label="trans('bookings.documents.fields.line_total')"
                    >
                        <p class="py-1.5 text-sm">
                            {{ money(lineTotal(line)) }}
                        </p>
                    </UFormField>
                    <UButton
                        class="self-end"
                        color="neutral"
                        variant="ghost"
                        icon="i-lucide-trash-2"
                        :aria-label="trans('bookings.documents.remove_line')"
                        :disabled="form.lines.length === 1"
                        @click="removeLine(index)"
                    />
                </div>
            </div>

            <div class="flex flex-wrap items-start justify-between gap-4">
                <UButton
                    color="neutral"
                    variant="subtle"
                    icon="i-lucide-plus"
                    :label="trans('bookings.documents.add_line')"
                    @click="addLine"
                />

                <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
                    <dt>{{ trans('bookings.documents.totals.subtotal') }}</dt>
                    <dd class="text-right">{{ money(subtotal) }}</dd>
                    <template v-if="taxRate">
                        <dt>{{ taxLabel }} ({{ taxRate }}%)</dt>
                        <dd class="text-right">{{ money(tax) }}</dd>
                    </template>
                    <dt class="font-semibold">
                        {{ trans('bookings.documents.totals.total') }}
                    </dt>
                    <dd class="text-right font-semibold">
                        {{ money(subtotal + tax) }}
                    </dd>
                </dl>
            </div>

            <p class="text-muted text-sm">
                {{
                    taxRate
                        ? trans('bookings.documents.tax_note', {
                              label: taxLabel,
                              rate: String(taxRate),
                          })
                        : trans('bookings.documents.no_tax_note')
                }}
            </p>
        </UPageCard>

        <UPageCard variant="subtle">
            <UFormField
                :label="trans(`bookings.documents.fields.${dateField}`)"
                :help="trans(`bookings.documents.fields.${dateField}_help`)"
                :error="errors[dateField]"
            >
                <UInput v-model="form.date" type="date" class="w-full" />
            </UFormField>

            <UFormField
                :label="trans('bookings.documents.fields.notes')"
                :help="trans('bookings.documents.fields.notes_help')"
                :error="errors.notes"
            >
                <UTextarea v-model="form.notes" :rows="4" class="w-full" />
            </UFormField>
        </UPageCard>
    </EditorLayout>
</template>
