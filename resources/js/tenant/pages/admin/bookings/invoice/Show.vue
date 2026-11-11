<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    index,
    send,
    voidInvoice,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/InvoiceController';
import DocumentSheet from '@/tenant/components/bookings/DocumentSheet.vue';
import type { BookingDocumentData } from '@/types';

const props = defineProps<{
    id: number;
    document: BookingDocumentData;
    status: string;
    statusLabel: string;
    issuedOn: string;
    dueOn: string | null;
    sentAt: string | null;
    quote: { number: string; url: string } | null;
    /** The customer's page. */
    link: string;
    bookingUrl: string;
    /** The user can manage payments, and it isn't void. */
    canManage: boolean;
}>();

const { trans } = useTrans();

const breadcrumbs = computed(() => [
    { label: trans('bookings.documents.invoices_title'), to: index.url() },
    { label: props.document.number },
]);

const meta = computed(() =>
    [
        trans('bookings.documents.issued_on', { date: props.issuedOn }),
        props.dueOn
            ? trans('bookings.documents.due_on', { date: props.dueOn })
            : null,
        props.sentAt
            ? trans('bookings.documents.sent_on', { date: props.sentAt })
            : null,
    ].filter((line): line is string => line !== null),
);

const busy = ref(false);

function post(url: string, confirmText?: string) {
    if (confirmText && !window.confirm(confirmText)) {
        return;
    }

    router.post(
        url,
        {},
        {
            preserveScroll: true,
            onStart: () => (busy.value = true),
            onFinish: () => (busy.value = false),
        },
    );
}

const statusColours: Record<string, 'success' | 'error' | 'neutral'> = {
    paid: 'success',
    overdue: 'error',
};
</script>

<template>
    <EditorLayout
        :title="
            trans('bookings.documents.invoice', { number: document.number })
        "
        :breadcrumbs="breadcrumbs"
        readonly
    >
        <template #badges>
            <UBadge
                :color="statusColours[status] ?? 'neutral'"
                variant="subtle"
                :label="statusLabel"
            />
        </template>

        <div class="flex flex-wrap gap-2">
            <UButton
                v-if="canManage"
                icon="i-lucide-send"
                :loading="busy"
                :label="
                    sentAt
                        ? trans('bookings.documents.send_again')
                        : trans('bookings.documents.send')
                "
                @click="post(send.url(id))"
            />
            <UButton
                :href="link"
                target="_blank"
                color="neutral"
                variant="subtle"
                icon="i-lucide-external-link"
                :label="trans('bookings.documents.open_link')"
            />
            <UButton
                v-if="canManage"
                color="error"
                variant="ghost"
                icon="i-lucide-ban"
                :loading="busy"
                :label="trans('bookings.documents.void')"
                @click="
                    post(
                        voidInvoice.url(id),
                        trans('bookings.documents.confirm_void'),
                    )
                "
            />
            <Link :href="bookingUrl" class="text-primary self-center text-sm">
                {{ trans('bookings.documents.view_booking') }}
            </Link>
            <Link
                v-if="quote"
                :href="quote.url"
                class="text-primary self-center text-sm"
            >
                {{
                    trans('bookings.documents.from_quote', {
                        number: quote.number,
                    })
                }}
            </Link>
        </div>

        <DocumentSheet
            :document="document"
            :heading="
                trans('bookings.documents.invoice', { number: document.number })
            "
            :meta="meta"
        />
    </EditorLayout>
</template>
