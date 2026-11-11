<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    cancel,
    index,
    send,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/GiftCardController';
import type { GiftCardDetail } from '@/types';

const props = defineProps<{
    giftCard: GiftCardDetail;
    canManage: boolean;
}>();

const { trans } = useTrans();

const breadcrumbs = computed(() => [
    { label: trans('bookings.gift_cards.title'), to: index.url() },
    { label: props.giftCard.code },
]);

const busy = ref(false);

function post(url: string) {
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

function cancelCard() {
    if (window.confirm(trans('bookings.gift_cards.confirm_cancel'))) {
        post(cancel.url(props.giftCard.id));
    }
}

const details = computed(() =>
    [
        [trans('bookings.gift_cards.columns.value'), props.giftCard.value],
        [trans('bookings.gift_cards.fields.balance'), props.giftCard.balance],
        [
            trans('bookings.gift_cards.fields.expires_on'),
            props.giftCard.expires_on,
        ],
        [
            trans('bookings.gift_cards.fields.method'),
            props.giftCard.method_label,
        ],
        [
            trans('bookings.gift_cards.fields.price_paid'),
            props.giftCard.price_paid,
        ],
        [trans('bookings.gift_cards.fields.fee'), props.giftCard.fee],
        [trans('bookings.gift_cards.fields.issued'), props.giftCard.issued_at],
        [trans('bookings.gift_cards.fields.sent'), props.giftCard.sent_at],
    ].filter((row): row is [string, string] => Boolean(row[1])),
);

const people = computed(() =>
    [
        [
            trans('bookings.gift_cards.fields.purchaser_name'),
            props.giftCard.purchaser_name,
        ],
        [
            trans('bookings.gift_cards.fields.purchaser_email'),
            props.giftCard.purchaser_email,
        ],
        [
            trans('bookings.gift_cards.fields.recipient_name'),
            props.giftCard.recipient_name,
        ],
        [
            trans('bookings.gift_cards.fields.recipient_email'),
            props.giftCard.recipient_email,
        ],
    ].filter((row): row is [string, string] => Boolean(row[1])),
);
</script>

<template>
    <EditorLayout
        :title="giftCard.code"
        :breadcrumbs="breadcrumbs"
        readonly
        narrow
    >
        <UPageCard
            variant="subtle"
            :title="trans('bookings.gift_cards.sections.card')"
        >
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-mono text-lg font-semibold">
                    {{ giftCard.code }}
                </span>
                <UBadge
                    :color="
                        giftCard.status === 'active' ? 'success' : 'neutral'
                    "
                    variant="subtle"
                    :label="giftCard.status_label"
                />
            </div>

            <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                <div v-for="[label, value] in details" :key="label">
                    <dt class="font-medium">{{ label }}</dt>
                    <dd class="text-muted">{{ value }}</dd>
                </div>
            </dl>

            <div class="flex flex-wrap gap-2">
                <UButton
                    :href="giftCard.link"
                    target="_blank"
                    color="neutral"
                    variant="subtle"
                    icon="i-lucide-external-link"
                    :label="trans('bookings.gift_cards.fields.link')"
                />
                <UButton
                    v-if="canManage && giftCard.canSend"
                    color="neutral"
                    variant="subtle"
                    icon="i-lucide-mail"
                    :label="trans('bookings.gift_cards.send')"
                    :loading="busy"
                    @click="post(send.url(giftCard.id))"
                />
                <UButton
                    v-if="canManage && giftCard.canCancel"
                    color="error"
                    variant="subtle"
                    icon="i-lucide-ban"
                    :label="trans('bookings.gift_cards.cancel')"
                    :loading="busy"
                    @click="cancelCard"
                />
            </div>
        </UPageCard>

        <UPageCard
            v-if="people.length || giftCard.message"
            variant="subtle"
            :title="trans('bookings.gift_cards.sections.people')"
        >
            <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                <div v-for="[label, value] in people" :key="label">
                    <dt class="font-medium">{{ label }}</dt>
                    <dd class="text-muted break-all">{{ value }}</dd>
                </div>
            </dl>
            <p
                v-if="giftCard.message"
                class="text-muted whitespace-pre-line text-sm"
            >
                {{ giftCard.message }}
            </p>
        </UPageCard>

        <UPageCard
            variant="subtle"
            :title="trans('bookings.gift_cards.sections.uses')"
        >
            <p v-if="!giftCard.uses.length" class="text-muted text-sm">
                {{ trans('bookings.gift_cards.no_uses') }}
            </p>
            <ul v-else class="flex flex-col gap-2 text-sm">
                <li
                    v-for="use in giftCard.uses"
                    :key="use.id"
                    class="flex flex-wrap items-center gap-2"
                >
                    <span class="font-medium">{{ use.amount }}</span>
                    <Link :href="use.booking_url" class="text-primary">
                        {{ use.customer_name }}
                    </Link>
                    <span class="text-muted">
                        {{ use.service_name }} · {{ use.when }}
                    </span>
                    <span v-if="use.refunded" class="text-muted">
                        {{
                            trans('bookings.payments.refunded', {
                                amount: use.refunded,
                            })
                        }}
                    </span>
                </li>
            </ul>
        </UPageCard>
    </EditorLayout>
</template>
