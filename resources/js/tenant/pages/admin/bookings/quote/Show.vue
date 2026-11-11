<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    accept,
    decline,
    edit,
    index,
    send,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/QuoteController';
import DocumentSheet from '@/tenant/components/bookings/DocumentSheet.vue';
import type { BookingDocumentData } from '@/types';

const props = defineProps<{
    id: number;
    document: BookingDocumentData;
    status: string;
    statusLabel: string;
    validUntil: string | null;
    sentAt: string | null;
    answeredAt: string | null;
    /** The customer's page. */
    link: string;
    bookingUrl: string;
    /** The user can manage payments, and the customer hasn't answered. */
    canManage: boolean;
}>();

const { trans } = useTrans();

const breadcrumbs = computed(() => [
    { label: trans('bookings.documents.quotes_title'), to: index.url() },
    { label: props.document.number },
]);

const meta = computed(() =>
    [
        props.validUntil
            ? trans('bookings.documents.valid_until', {
                  date: props.validUntil,
              })
            : null,
        props.sentAt
            ? trans('bookings.documents.sent_on', { date: props.sentAt })
            : null,
        props.answeredAt
            ? trans('bookings.documents.answered_on', {
                  date: props.answeredAt,
              })
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
</script>

<template>
    <EditorLayout
        :title="trans('bookings.documents.quote', { number: document.number })"
        :breadcrumbs="breadcrumbs"
        readonly
    >
        <template #badges>
            <UBadge color="neutral" variant="subtle" :label="statusLabel" />
        </template>

        <div class="flex flex-wrap gap-2">
            <UButton
                v-if="canManage"
                as="Link"
                :href="edit.url(id)"
                color="neutral"
                variant="subtle"
                icon="i-lucide-pencil"
                :label="trans('bookings.documents.edit')"
            />
            <UButton
                v-if="canManage"
                icon="i-lucide-send"
                :loading="busy"
                :label="
                    status === 'draft'
                        ? trans('bookings.documents.send')
                        : trans('bookings.documents.send_again')
                "
                @click="post(send.url(id))"
            />
            <UButton
                v-if="canManage"
                color="success"
                variant="subtle"
                icon="i-lucide-check"
                :loading="busy"
                :label="trans('bookings.documents.mark_accepted')"
                @click="
                    post(
                        accept.url(id),
                        trans('bookings.documents.confirm_accept'),
                    )
                "
            />
            <UButton
                v-if="canManage"
                color="neutral"
                variant="ghost"
                icon="i-lucide-x"
                :loading="busy"
                :label="trans('bookings.documents.mark_declined')"
                @click="post(decline.url(id))"
            />
            <UButton
                v-if="status !== 'draft'"
                :href="link"
                target="_blank"
                color="neutral"
                variant="ghost"
                icon="i-lucide-external-link"
                :label="trans('bookings.documents.open_link')"
            />
            <Link :href="bookingUrl" class="text-primary self-center text-sm">
                {{ trans('bookings.documents.view_booking') }}
            </Link>
        </div>

        <DocumentSheet
            :document="document"
            :heading="
                trans('bookings.documents.quote', { number: document.number })
            "
            :meta="meta"
        />
    </EditorLayout>
</template>
