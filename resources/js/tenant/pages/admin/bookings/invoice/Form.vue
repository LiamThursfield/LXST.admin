<script setup lang="ts">
import {
    index,
    store,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/InvoiceController';
import DocumentForm from '@/tenant/components/bookings/DocumentForm.vue';
import type { DocumentFormBooking, DocumentFormLine } from '@/types';

defineProps<{
    booking: DocumentFormBooking;
    /** The accepted quote its lines came from. */
    quoteId: number | null;
    lines: DocumentFormLine[];
    date: string | null;
    taxRate: number | null;
    taxLabel: string;
    currency: string;
}>();

const { trans } = useTrans();

const breadcrumbs = computed(() => [
    { label: trans('bookings.documents.invoices_title'), to: index.url() },
    { label: trans('bookings.documents.new_invoice_title') },
]);
</script>

<template>
    <DocumentForm
        :title="trans('bookings.documents.new_invoice_title')"
        :breadcrumbs="breadcrumbs"
        :booking="booking"
        :lines="lines"
        date-field="due_on"
        :date="date"
        :tax-rate="taxRate"
        :tax-label="taxLabel"
        :currency="currency"
        :extra="{ quote_id: quoteId }"
        :help="trans('bookings.documents.invoice_help')"
        :save-label="trans('bookings.documents.issue')"
        :send-label="trans('bookings.documents.issue_and_send')"
        :action="store(booking.id)"
    />
</template>
