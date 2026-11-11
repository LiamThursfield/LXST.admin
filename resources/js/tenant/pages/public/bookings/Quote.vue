<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    accept,
    decline,
} from '@/actions/App/Http/Controllers/Tenant/Public/Bookings/QuoteController';
import PublicLayout from '@/layouts/PublicLayout.vue';
import DocumentSheet from '@/tenant/components/bookings/DocumentSheet.vue';
import type { BookingDocumentData } from '@/types';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    publicId: string;
    document: BookingDocumentData;
    status: 'sent' | 'accepted' | 'declined' | 'expired';
    statusLabel: string;
    validUntil: string | null;
    /** Whether it can still be accepted or declined. */
    open: boolean;
}>();

const { trans } = useTrans();

const message = computed(() => {
    const business = props.document.business;

    return (
        {
            accepted: trans('bookings.documents.public.accepted', { business }),
            declined: trans('bookings.documents.public.declined'),
            expired: trans('bookings.documents.public.expired', { business }),
        } as Record<string, string>
    )[props.status];
});

const busy = ref(false);

function answer(url: string, confirmText?: string) {
    if (confirmText && !window.confirm(confirmText)) {
        return;
    }

    router.post(
        url,
        {},
        {
            onStart: () => (busy.value = true),
            onFinish: () => (busy.value = false),
        },
    );
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head
            :title="
                trans('bookings.documents.public.quote_title', {
                    number: document.number,
                })
            "
        />

        <DocumentSheet
            :document="document"
            :heading="
                trans('bookings.documents.public.quote_title', {
                    number: document.number,
                })
            "
            :meta="
                validUntil
                    ? [
                          trans('bookings.documents.valid_until', {
                              date: validUntil,
                          }),
                      ]
                    : []
            "
        />

        <UAlert
            v-if="message"
            :color="status === 'accepted' ? 'success' : 'neutral'"
            variant="subtle"
            :title="message"
        />

        <div v-if="open" class="flex flex-wrap gap-2 print:hidden">
            <UButton
                size="lg"
                icon="i-lucide-check"
                :loading="busy"
                :label="trans('bookings.documents.public.accept')"
                @click="answer(accept.url(publicId))"
            />
            <UButton
                size="lg"
                color="neutral"
                variant="ghost"
                :loading="busy"
                :label="trans('bookings.documents.public.decline')"
                @click="
                    answer(
                        decline.url(publicId),
                        trans('bookings.documents.public.confirm_decline'),
                    )
                "
            />
        </div>
    </div>
</template>
