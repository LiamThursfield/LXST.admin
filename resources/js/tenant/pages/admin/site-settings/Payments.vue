<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    connect,
    disconnect,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/SiteSettings/PaymentSettingsController';
import SiteSettingsSubLayout from '@/sublayouts/admin/SiteSettingsSubLayout.vue';

const props = defineProps<{
    /** Whether the platform has its own Stripe keys, without which nothing can be connected. */
    platformReady: boolean;
    connected: boolean;
    detailsSubmitted: boolean;
    chargesEnabled: boolean;
    fee: { percent: number; fixed: string | null } | null;
    canManage: boolean;
    giftCards: {
        gift_card_months: number | null;
        gift_cards_online: boolean;
        /** The public page customers buy them on. */
        shop_url: string;
    };
    documents: {
        tax_rate: number | null;
        tax_label: string;
        tax_number: string | null;
        business_details: string | null;
        quote_valid_days: number | null;
        invoice_due_days: number | null;
    };
}>();

const { trans } = useTrans();

const status = computed(() => {
    if (!props.connected) {
        return trans('site_settings.payments.not_connected');
    }

    return trans(
        props.chargesEnabled
            ? 'site_settings.payments.ready'
            : 'site_settings.payments.incomplete',
    );
});

const feeText = computed(() => {
    if (!props.fee) {
        return null;
    }

    const percent = String(props.fee.percent);

    if (props.fee.percent > 0 && props.fee.fixed) {
        return trans('site_settings.payments.fee_both', {
            percent,
            fixed: props.fee.fixed,
        });
    }

    return props.fee.fixed
        ? trans('site_settings.payments.fee_fixed', { fixed: props.fee.fixed })
        : trans('site_settings.payments.fee_value', { percent });
});

const connecting = ref(false);

function onConnect() {
    connecting.value = true;
    router.post(
        connect.url(),
        {},
        { onFinish: () => (connecting.value = false) },
    );
}

const form = useForm({
    gift_card_months: props.giftCards.gift_card_months as number | null,
    gift_cards_online: props.giftCards.gift_cards_online,
    tax_rate: props.documents.tax_rate as number | null,
    tax_label: props.documents.tax_label,
    tax_number: props.documents.tax_number ?? '',
    business_details: props.documents.business_details ?? '',
    quote_valid_days: props.documents.quote_valid_days as number | null,
    invoice_due_days: props.documents.invoice_due_days as number | null,
});

/** An emptied number field means the setting is off. */
const blankToNull = (value: number | string | null) =>
    value === null || String(value) === '' ? null : value;

function discard() {
    form.reset();
    form.clearErrors();
}

function onSubmit() {
    if (!props.canManage) return;

    form.transform((data) => ({
        ...data,
        gift_card_months: blankToNull(data.gift_card_months),
        tax_rate: blankToNull(data.tax_rate),
        quote_valid_days: blankToNull(data.quote_valid_days),
        invoice_due_days: blankToNull(data.invoice_due_days),
    })).submit(update(), {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
}

function onDisconnect() {
    if (window.confirm(trans('site_settings.payments.confirm_disconnect'))) {
        router.delete(disconnect.url());
    }
}
</script>

<template>
    <SiteSettingsSubLayout>
        <SettingsPage
            :title="trans('site_settings.payments.title')"
            :description="trans('site_settings.payments.description')"
            :readonly="!canManage"
            form-id="payment-settings"
            :is-dirty="form.isDirty"
            :processing="form.processing"
            @discard="discard"
        >
            <UAlert
                v-if="!platformReady"
                color="warning"
                variant="subtle"
                :title="trans('site_settings.payments.platform_not_ready')"
            />

            <SettingsRow :label="trans('site_settings.payments.status')">
                <div class="flex flex-col items-start gap-3">
                    <div class="flex items-center gap-2 text-sm">
                        <UIcon
                            :name="
                                chargesEnabled
                                    ? 'i-lucide-circle-check'
                                    : 'i-lucide-circle-dashed'
                            "
                            :class="
                                chargesEnabled ? 'text-success' : 'text-muted'
                            "
                        />
                        {{ status }}
                    </div>

                    <div v-if="canManage && platformReady" class="flex gap-2">
                        <UButton
                            v-if="!chargesEnabled"
                            icon="i-lucide-external-link"
                            :loading="connecting"
                            :label="
                                trans(
                                    connected
                                        ? 'site_settings.payments.continue'
                                        : 'site_settings.payments.connect',
                                )
                            "
                            @click="onConnect"
                        />
                        <UButton
                            v-if="connected"
                            color="neutral"
                            variant="ghost"
                            :label="trans('site_settings.payments.disconnect')"
                            @click="onDisconnect"
                        />
                    </div>
                </div>
            </SettingsRow>

            <SettingsRow
                v-if="feeText"
                :label="trans('site_settings.payments.fee')"
                :description="trans('site_settings.payments.fee_help')"
            >
                <p class="text-sm">{{ feeText }}</p>
            </SettingsRow>

            <form
                id="payment-settings"
                class="flex flex-col"
                @submit.prevent="onSubmit"
            >
                <SettingsRow
                    :label="trans('site_settings.payments.gift_card_months')"
                    :description="
                        trans('site_settings.payments.gift_card_months_help')
                    "
                >
                    <UFormField :error="form.errors.gift_card_months">
                        <UInput
                            v-model.nullable="form.gift_card_months"
                            type="number"
                            min="1"
                            max="120"
                            :disabled="!canManage"
                            class="w-32"
                        />
                    </UFormField>
                </SettingsRow>

                <SettingsRow
                    :label="trans('site_settings.payments.gift_cards_online')"
                    :description="
                        chargesEnabled
                            ? trans(
                                  'site_settings.payments.gift_cards_online_help',
                              )
                            : trans(
                                  'site_settings.payments.gift_cards_needs_stripe',
                              )
                    "
                >
                    <div class="flex flex-col items-start gap-2">
                        <USwitch
                            v-model="form.gift_cards_online"
                            :disabled="!canManage"
                            :aria-label="
                                trans(
                                    'site_settings.payments.gift_cards_online',
                                )
                            "
                        />
                        <UButton
                            v-if="giftCards.gift_cards_online && chargesEnabled"
                            :href="giftCards.shop_url"
                            target="_blank"
                            size="xs"
                            color="neutral"
                            variant="ghost"
                            icon="i-lucide-external-link"
                            :label="trans('site_settings.payments.shop_link')"
                        />
                    </div>
                </SettingsRow>
                <SettingsRow
                    :label="trans('site_settings.payments.tax_rate')"
                    :description="trans('site_settings.payments.tax_rate_help')"
                >
                    <UFormField :error="form.errors.tax_rate">
                        <UInput
                            v-model.nullable="form.tax_rate"
                            type="number"
                            min="0.01"
                            max="100"
                            step="0.01"
                            :disabled="!canManage"
                            class="w-32"
                        />
                    </UFormField>
                </SettingsRow>
                <SettingsRow
                    :label="trans('site_settings.payments.tax_label')"
                    :description="
                        trans('site_settings.payments.tax_label_help')
                    "
                >
                    <UFormField :error="form.errors.tax_label">
                        <UInput
                            v-model="form.tax_label"
                            :disabled="!canManage"
                            class="w-32"
                        />
                    </UFormField>
                </SettingsRow>
                <SettingsRow
                    :label="trans('site_settings.payments.tax_number')"
                    :description="
                        trans('site_settings.payments.tax_number_help')
                    "
                >
                    <UFormField :error="form.errors.tax_number">
                        <UInput
                            v-model="form.tax_number"
                            :disabled="!canManage"
                            class="w-full"
                        />
                    </UFormField>
                </SettingsRow>
                <SettingsRow
                    :label="trans('site_settings.payments.business_details')"
                    :description="
                        trans('site_settings.payments.business_details_help')
                    "
                >
                    <UFormField :error="form.errors.business_details">
                        <UTextarea
                            v-model="form.business_details"
                            :rows="4"
                            :disabled="!canManage"
                            class="w-full"
                        />
                    </UFormField>
                </SettingsRow>
                <SettingsRow
                    :label="trans('site_settings.payments.quote_valid_days')"
                    :description="
                        trans('site_settings.payments.quote_valid_days_help')
                    "
                >
                    <UFormField :error="form.errors.quote_valid_days">
                        <UInput
                            v-model.nullable="form.quote_valid_days"
                            type="number"
                            min="1"
                            max="365"
                            step="1"
                            :disabled="!canManage"
                            class="w-32"
                        />
                    </UFormField>
                </SettingsRow>
                <SettingsRow
                    :label="trans('site_settings.payments.invoice_due_days')"
                    :description="
                        trans('site_settings.payments.invoice_due_days_help')
                    "
                >
                    <UFormField :error="form.errors.invoice_due_days">
                        <UInput
                            v-model.nullable="form.invoice_due_days"
                            type="number"
                            min="0"
                            max="365"
                            step="1"
                            :disabled="!canManage"
                            class="w-32"
                        />
                    </UFormField>
                </SettingsRow>
            </form>
        </SettingsPage>
    </SiteSettingsSubLayout>
</template>
