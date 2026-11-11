<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    index,
    store,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/GiftCardController';
import type { SelectItem } from '@/types';

const props = defineProps<{
    kinds: SelectItem[];
    /** Services with a set price, which a voucher can be for. */
    services: SelectItem[];
    methods: SelectItem[];
    /** The business's usual last day for a card issued today. */
    defaultExpiry: string | null;
}>();

const { trans } = useTrans();

const form = useForm({
    kind: 'amount',
    amount: '',
    service_id: props.services[0]?.value as number | undefined,
    method: 'card',
    purchaser_name: '',
    purchaser_email: '',
    recipient_name: '',
    recipient_email: '',
    message: '',
    expires_on: props.defaultExpiry ?? '',
});

const kindItems = computed(() =>
    props.services.length
        ? props.kinds
        : props.kinds.filter((kind) => kind.value === 'amount'),
);

const breadcrumbs = computed(() => [
    { label: trans('bookings.gift_cards.title'), to: index.url() },
    { label: trans('bookings.gift_cards.create_title') },
]);

function submit() {
    form.transform((data) => ({
        ...data,
        expires_on: data.expires_on || null,
        amount: data.kind === 'amount' ? data.amount : null,
        service_id: data.kind === 'service' ? data.service_id : null,
    })).submit(store());
}
</script>

<template>
    <EditorLayout
        :title="trans('bookings.gift_cards.create_title')"
        :breadcrumbs="breadcrumbs"
        :is-dirty="form.isDirty"
        :processing="form.processing"
        :submit-label="trans('bookings.gift_cards.create')"
        narrow
        @submit="submit"
    >
        <UPageCard
            variant="subtle"
            :title="trans('bookings.gift_cards.sections.card')"
        >
            <UFormField
                :label="trans('bookings.gift_cards.fields.kind')"
                name="kind"
                :error="form.errors.kind"
                required
            >
                <URadioGroup
                    v-model="form.kind"
                    :items="kindItems"
                    orientation="horizontal"
                />
            </UFormField>

            <UFormField
                v-if="form.kind === 'amount'"
                :label="
                    trans('bookings.gift_cards.fields.amount', {
                        currency: 'GBP',
                    })
                "
                name="amount"
                :error="form.errors.amount"
                required
            >
                <UInput
                    v-model="form.amount"
                    type="number"
                    min="0.01"
                    step="0.01"
                    class="w-full"
                />
            </UFormField>

            <UFormField
                v-else
                :label="trans('bookings.gift_cards.fields.service')"
                :help="trans('bookings.gift_cards.fields.service_help')"
                name="service_id"
                :error="form.errors.service_id"
                required
            >
                <USelect
                    v-model="form.service_id"
                    :items="services"
                    class="w-full"
                />
            </UFormField>

            <UFormField
                :label="trans('bookings.gift_cards.fields.expires_on')"
                :help="trans('bookings.gift_cards.fields.expires_on_help')"
                name="expires_on"
                :error="form.errors.expires_on"
            >
                <UInput v-model="form.expires_on" type="date" class="w-full" />
            </UFormField>
        </UPageCard>

        <UPageCard
            variant="subtle"
            :title="trans('bookings.gift_cards.sections.payment')"
        >
            <UFormField
                :label="trans('bookings.gift_cards.fields.method')"
                :help="
                    form.method === 'online'
                        ? trans('bookings.gift_cards.fields.method_online_help')
                        : undefined
                "
                name="method"
                :error="form.errors.method"
                required
            >
                <USelect
                    v-model="form.method"
                    :items="methods"
                    class="w-full"
                />
            </UFormField>
        </UPageCard>

        <UPageCard
            variant="subtle"
            :title="trans('bookings.gift_cards.sections.people')"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    :label="trans('bookings.gift_cards.fields.purchaser_name')"
                    name="purchaser_name"
                    :error="form.errors.purchaser_name"
                >
                    <UInput v-model="form.purchaser_name" class="w-full" />
                </UFormField>
                <UFormField
                    :label="trans('bookings.gift_cards.fields.purchaser_email')"
                    name="purchaser_email"
                    :error="form.errors.purchaser_email"
                    :required="form.method === 'online'"
                >
                    <UInput
                        v-model="form.purchaser_email"
                        type="email"
                        class="w-full"
                    />
                </UFormField>
                <UFormField
                    :label="trans('bookings.gift_cards.fields.recipient_name')"
                    name="recipient_name"
                    :error="form.errors.recipient_name"
                >
                    <UInput v-model="form.recipient_name" class="w-full" />
                </UFormField>
                <UFormField
                    :label="trans('bookings.gift_cards.fields.recipient_email')"
                    :help="
                        trans('bookings.gift_cards.fields.recipient_email_help')
                    "
                    name="recipient_email"
                    :error="form.errors.recipient_email"
                >
                    <UInput
                        v-model="form.recipient_email"
                        type="email"
                        class="w-full"
                    />
                </UFormField>
            </div>

            <UFormField
                :label="trans('bookings.gift_cards.fields.message')"
                name="message"
                :error="form.errors.message"
            >
                <UTextarea v-model="form.message" :rows="3" class="w-full" />
            </UFormField>
        </UPageCard>
    </EditorLayout>
</template>
