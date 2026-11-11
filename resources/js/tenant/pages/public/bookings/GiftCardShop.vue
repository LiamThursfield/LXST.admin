<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { store } from '@/actions/App/Http/Controllers/Tenant/Public/Bookings/GiftCardShopController';
import PublicLayout from '@/layouts/PublicLayout.vue';
import type { SelectItem } from '@/types';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    business: string;
    kinds: SelectItem[];
    /** Services with a set price that can be booked online. */
    services: SelectItem[];
    /** In pounds, for the amount field. */
    minimum: string;
    maximum: string;
    minimumLabel: string;
    maximumLabel: string;
}>();

const { trans } = useTrans();

const form = useForm({
    kind: 'amount',
    amount: '',
    service_id: props.services[0]?.value as number | undefined,
    purchaser_name: '',
    purchaser_email: '',
    recipient_name: '',
    recipient_email: '',
    message: '',
});

function submit() {
    form.transform((data) => ({
        ...data,
        amount: data.kind === 'amount' ? data.amount : null,
        service_id: data.kind === 'service' ? data.service_id : null,
    })).submit(store());
}
</script>

<template>
    <div class="mx-auto flex max-w-lg flex-col gap-6 px-4 py-12">
        <Head :title="trans('bookings.gift_cards.public.title')" />

        <div class="flex flex-col items-center gap-2 text-center">
            <UIcon name="i-lucide-gift" class="text-primary size-12" />
            <h1 class="text-xl font-semibold">
                {{ trans('bookings.gift_cards.public.heading') }}
            </h1>
            <p class="text-muted">
                {{ trans('bookings.gift_cards.public.intro', { business }) }}
            </p>
        </div>

        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <div class="flex flex-col gap-4">
                <UFormField
                    v-if="kinds.length > 1"
                    :label="trans('bookings.gift_cards.public.kind')"
                    name="kind"
                    :error="form.errors.kind"
                >
                    <URadioGroup
                        v-model="form.kind"
                        :items="kinds"
                        orientation="horizontal"
                    />
                </UFormField>

                <UFormField
                    v-if="form.kind === 'amount'"
                    :label="
                        trans('bookings.gift_cards.public.amount', {
                            minimum: minimumLabel,
                            maximum: maximumLabel,
                        })
                    "
                    name="amount"
                    :error="form.errors.amount"
                    required
                >
                    <UInput
                        v-model="form.amount"
                        type="number"
                        :min="minimum"
                        :max="maximum"
                        step="0.01"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    v-else
                    :label="trans('bookings.gift_cards.public.service')"
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
            </div>

            <fieldset class="flex flex-col gap-4">
                <legend class="mb-2 font-medium">
                    {{ trans('bookings.gift_cards.public.purchaser') }}
                </legend>
                <UFormField
                    :label="trans('bookings.gift_cards.fields.purchaser_name')"
                    name="purchaser_name"
                    :error="form.errors.purchaser_name"
                    required
                >
                    <UInput
                        v-model="form.purchaser_name"
                        autocomplete="name"
                        class="w-full"
                    />
                </UFormField>
                <UFormField
                    :label="trans('bookings.gift_cards.fields.purchaser_email')"
                    name="purchaser_email"
                    :error="form.errors.purchaser_email"
                    required
                >
                    <UInput
                        v-model="form.purchaser_email"
                        type="email"
                        autocomplete="email"
                        class="w-full"
                    />
                </UFormField>
            </fieldset>

            <fieldset class="flex flex-col gap-4">
                <legend class="mb-2 font-medium">
                    {{ trans('bookings.gift_cards.public.recipient') }}
                </legend>
                <p class="text-muted -mt-2 text-sm">
                    {{ trans('bookings.gift_cards.public.recipient_help') }}
                </p>
                <UFormField
                    :label="trans('bookings.gift_cards.fields.recipient_name')"
                    name="recipient_name"
                    :error="form.errors.recipient_name"
                >
                    <UInput v-model="form.recipient_name" class="w-full" />
                </UFormField>
                <UFormField
                    :label="trans('bookings.gift_cards.fields.recipient_email')"
                    name="recipient_email"
                    :error="form.errors.recipient_email"
                >
                    <UInput
                        v-model="form.recipient_email"
                        type="email"
                        class="w-full"
                    />
                </UFormField>
                <UFormField
                    :label="trans('bookings.gift_cards.fields.message')"
                    name="message"
                    :error="form.errors.message"
                >
                    <UTextarea
                        v-model="form.message"
                        :rows="3"
                        class="w-full"
                    />
                </UFormField>
            </fieldset>

            <UButton
                type="submit"
                size="lg"
                icon="i-lucide-lock"
                block
                :loading="form.processing"
                :label="trans('bookings.gift_cards.public.buy')"
            />
        </form>
    </div>
</template>
