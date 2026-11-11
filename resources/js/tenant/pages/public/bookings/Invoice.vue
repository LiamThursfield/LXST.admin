<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { pay } from '@/actions/App/Http/Controllers/Tenant/Public/Bookings/InvoiceController';
import PublicLayout from '@/layouts/PublicLayout.vue';
import DocumentSheet from '@/tenant/components/bookings/DocumentSheet.vue';
import type { BookingDocumentData } from '@/types';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    publicId: string;
    document: BookingDocumentData;
    status: 'issued' | 'sent' | 'paid' | 'overdue' | 'void';
    statusLabel: string;
    issuedOn: string;
    dueOn: string | null;
    /** Whether there's enough owed to pay online, and the business takes payments. */
    canPay: boolean;
}>();

const { trans } = useTrans();

const meta = computed(() =>
    [
        trans('bookings.documents.issued_on', { date: props.issuedOn }),
        props.dueOn
            ? trans('bookings.documents.due_on', { date: props.dueOn })
            : null,
    ].filter((line): line is string => line !== null),
);

const paying = ref(false);

function payNow() {
    router.post(
        pay.url(props.publicId),
        {},
        {
            onStart: () => (paying.value = true),
            onFinish: () => (paying.value = false),
        },
    );
}

function print() {
    window.print();
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head
            :title="
                trans('bookings.documents.public.invoice_title', {
                    number: document.number,
                })
            "
        />

        <DocumentSheet
            :document="document"
            :heading="
                trans('bookings.documents.public.invoice_title', {
                    number: document.number,
                })
            "
            :meta="meta"
        />

        <UAlert
            v-if="status === 'paid' || status === 'void'"
            :color="status === 'paid' ? 'success' : 'neutral'"
            variant="subtle"
            :title="trans(`bookings.documents.public.${status}`)"
        />

        <div class="flex flex-wrap gap-2 print:hidden">
            <UButton
                v-if="canPay && status !== 'void'"
                size="lg"
                icon="i-lucide-lock"
                :loading="paying"
                :label="
                    trans('bookings.documents.public.pay', {
                        amount: document.owed ?? '',
                    })
                "
                @click="payNow"
            />
            <UButton
                size="lg"
                color="neutral"
                variant="subtle"
                icon="i-lucide-printer"
                :label="trans('bookings.documents.public.print')"
                @click="print"
            />
        </div>
    </div>
</template>
