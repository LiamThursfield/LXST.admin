<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    business: string;
    status: 'pending' | 'active' | 'used' | 'expired' | 'cancelled';
    statusLabel: string;
    value: string;
    isService: boolean;
    price: string;
    /** Only once it's paid for. */
    code: string | null;
    balance: string | null;
    recipientName: string | null;
    purchaserName: string | null;
    message: string | null;
    expiresOn: string | null;
    /** Set while it can still be paid for. */
    payUrl: string | null;
}>();

const { trans } = useTrans();

/** Why there's no card to show, when there isn't one. */
const notice = computed(() => {
    if (props.status === 'cancelled') {
        return trans('bookings.gift_cards.public.cancelled');
    }

    if (props.code === null) {
        return props.payUrl || props.status === 'pending'
            ? trans('bookings.gift_cards.public.pending')
            : trans('bookings.gift_cards.public.expired');
    }

    return props.status === 'expired' && props.expiresOn
        ? trans('bookings.gift_cards.public.past_date', {
              date: props.expiresOn,
          })
        : null;
});
</script>

<template>
    <div class="mx-auto flex max-w-md flex-col items-center gap-6 px-4 py-16">
        <Head :title="trans('bookings.gift_cards.public.card_title')" />

        <UIcon name="i-lucide-gift" class="text-primary size-12" />

        <h1 class="text-xl font-semibold">{{ business }}</h1>

        <div
            class="border-default flex w-full flex-col items-center gap-3 rounded-xl border p-6 text-center"
        >
            <p class="text-2xl font-semibold">{{ value }}</p>
            <p v-if="recipientName" class="text-muted">
                {{
                    trans('bookings.gift_cards.public.for', {
                        name: recipientName,
                    })
                }}
            </p>
            <p v-if="message" class="whitespace-pre-line text-sm italic">
                {{ message }}
            </p>
            <p v-if="purchaserName" class="text-muted text-sm">
                {{
                    trans('bookings.gift_cards.public.from', {
                        name: purchaserName,
                    })
                }}
            </p>

            <template v-if="code">
                <USeparator />
                <p class="text-muted text-sm">
                    {{ trans('bookings.gift_cards.public.code') }}
                </p>
                <p class="font-mono text-xl font-semibold tracking-wider">
                    {{ code }}
                </p>
                <p v-if="balance && !isService" class="text-sm">
                    {{ trans('bookings.gift_cards.public.balance') }}:
                    {{ balance }}
                </p>
                <UBadge
                    :color="status === 'active' ? 'success' : 'neutral'"
                    variant="subtle"
                    :label="statusLabel"
                />
                <p
                    v-if="expiresOn && status === 'active'"
                    class="text-muted text-sm"
                >
                    {{
                        trans('bookings.gift_cards.public.use_by', {
                            date: expiresOn,
                        })
                    }}
                </p>
            </template>
        </div>

        <p v-if="notice" class="text-center">{{ notice }}</p>

        <UButton
            v-if="payUrl"
            :href="payUrl"
            size="lg"
            icon="i-lucide-lock"
            :label="trans('bookings.gift_cards.public.pay', { price })"
        />
    </div>
</template>
