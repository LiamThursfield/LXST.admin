<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    connect,
    disconnect,
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
        </SettingsPage>
    </SiteSettingsSubLayout>
</template>
