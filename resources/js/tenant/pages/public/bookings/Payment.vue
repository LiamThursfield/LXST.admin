<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    business: string;
    status: 'pending' | 'paid' | 'failed' | 'expired';
    expired: boolean;
    amount: string;
    service: string;
    /** Set while the link can still be paid. */
    payUrl: string | null;
}>();

const { trans } = useTrans();

const message = computed(() => {
    if (props.status === 'paid') {
        return trans('bookings.payments.public.paid', {
            amount: props.amount,
            service: props.service,
        });
    }

    if (props.expired) {
        return trans('bookings.payments.public.expired', {
            business: props.business,
        });
    }

    return props.status === 'failed'
        ? trans('bookings.payments.public.failed', { business: props.business })
        : trans('bookings.payments.public.pending');
});
</script>

<template>
    <div class="mx-auto flex max-w-md flex-col items-center gap-6 px-4 py-16">
        <Head :title="trans('bookings.payments.public.title')" />

        <UIcon
            :name="
                status === 'paid'
                    ? 'i-lucide-circle-check'
                    : 'i-lucide-credit-card'
            "
            class="size-12"
            :class="status === 'paid' ? 'text-success' : 'text-muted'"
        />

        <h1 class="text-xl font-semibold">{{ business }}</h1>

        <p class="text-center">{{ message }}</p>

        <UButton
            v-if="payUrl"
            :href="payUrl"
            size="lg"
            icon="i-lucide-lock"
            :label="trans('bookings.payments.public.pay', { amount })"
        />
    </div>
</template>
