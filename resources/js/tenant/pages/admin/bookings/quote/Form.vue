<script setup lang="ts">
import {
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/QuoteController';
import DocumentForm from '@/tenant/components/bookings/DocumentForm.vue';
import type { DocumentFormBooking, DocumentFormLine } from '@/types';

const props = defineProps<{
    booking: DocumentFormBooking;
    /** Set when editing one. */
    quote: { id: number; number: string; sent: boolean } | null;
    lines: DocumentFormLine[];
    notes: string | null;
    date: string | null;
    taxRate: number | null;
    taxLabel: string;
    currency: string;
}>();

const { trans } = useTrans();

const title = computed(() =>
    props.quote
        ? trans('bookings.documents.edit_quote_title', {
              number: props.quote.number,
          })
        : trans('bookings.documents.new_quote_title'),
);

const breadcrumbs = computed(() => [
    { label: trans('bookings.documents.quotes_title'), to: index.url() },
    { label: title.value },
]);
</script>

<template>
    <DocumentForm
        :title="title"
        :breadcrumbs="breadcrumbs"
        :booking="booking"
        :lines="lines"
        :notes="notes"
        date-field="valid_until"
        :date="date"
        :tax-rate="taxRate"
        :tax-label="taxLabel"
        :currency="currency"
        :help="trans('bookings.documents.quote_help')"
        :save-label="trans('bookings.documents.save')"
        :send-label="
            quote?.sent
                ? trans('bookings.documents.send_again')
                : trans('bookings.documents.save_and_send')
        "
        :action="quote ? update(quote.id) : store(booking.id)"
    />
</template>
