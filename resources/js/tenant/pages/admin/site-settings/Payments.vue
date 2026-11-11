<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    connect,
    disconnect,
    giftCards as updateGiftCards,
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
});

function discard() {
    form.reset();
    form.clearErrors();
}

function onSubmit() {
    if (!props.canManage) return;

    form.transform((data) => ({
        ...data,
        // An emptied number field means they never expire
        gift_card_months:
            data.gift_card_months === null ||
            String(data.gift_card_months) === ''
                ? null
                : data.gift_card_months,
    })).submit(updateGiftCards(), {
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
            form-id="gift-card-settings"
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
                id="gift-card-settings"
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
            </form>
        </SettingsPage>
    </SiteSettingsSubLayout>
</template>
