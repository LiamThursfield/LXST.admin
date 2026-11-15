<script lang="ts" setup>
import { useHttp } from '@inertiajs/vue3';
import type { RouteDefinition } from '@/wayfinder';

/**
 * Turning two-factor login on (scan a QR code, then confirm a code from the app) and off, and
 * showing or remaking the recovery codes. Each app passes its own Fortify routes.
 */
const props = defineProps<{
    enabled: boolean;
    routes: {
        enable: () => RouteDefinition<'post'>;
        confirm: () => RouteDefinition<'post'>;
        disable: () => RouteDefinition<'delete'>;
        qrCode: () => RouteDefinition<'get'>;
        secretKey: () => RouteDefinition<'get'>;
        recoveryCodes: () => RouteDefinition<'get'>;
        regenerateRecoveryCodes: () => RouteDefinition<'post'>;
    };
}>();

const { trans } = useTrans();

const toast = useToast();

const http = useHttp();

const turningOn = ref(false);
const turningOff = ref(false);

// Setting up: the QR code and key for the app, then the code it shows
const setupOpen = ref(false);
const qrCodeSvg = ref<string | null>(null);
const secretKey = ref<string | null>(null);
const codeDigits = ref<string[]>([]);
const confirmForm = useForm({ code: '' });

const qrCodeSrc = computed(() =>
    qrCodeSvg.value
        ? `data:image/svg+xml;base64,${btoa(qrCodeSvg.value)}`
        : null,
);

// Recovery codes, once asked for
const recoveryCodes = ref<string[] | null>(null);
const loadingRecoveryCodes = ref(false);

function failed() {
    toast.add({
        title: trans('settings.two_factor.failed'),
        icon: 'i-lucide-circle-alert',
        color: 'error',
    });
}

function turnOn() {
    turningOn.value = true;

    router.post(
        props.routes.enable().url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => openSetup(),
            onFinish: () => (turningOn.value = false),
        },
    );
}

async function openSetup() {
    try {
        const qrCode = await http.get(props.routes.qrCode().url);
        const key = await http.get(props.routes.secretKey().url);

        qrCodeSvg.value = (qrCode as { svg: string }).svg;
        secretKey.value = (key as { secretKey: string }).secretKey;
    } catch {
        failed();

        return;
    }

    codeDigits.value = [];
    confirmForm.reset();
    confirmForm.clearErrors();
    setupOpen.value = true;
}

function confirm() {
    confirmForm.code = codeDigits.value.join('');

    confirmForm.submit(props.routes.confirm(), {
        errorBag: 'confirmTwoFactorAuthentication',
        preserveScroll: true,
        onSuccess: async () => {
            setupOpen.value = false;
            toast.add({
                title: trans('settings.two_factor.turned_on'),
                icon: 'i-lucide-check',
                color: 'success',
            });
            await showRecoveryCodes();
        },
        onError: () => (codeDigits.value = []),
    });
}

function turnOff() {
    turningOff.value = true;

    router.delete(props.routes.disable().url, {
        preserveScroll: true,
        onSuccess: () => {
            recoveryCodes.value = null;
            toast.add({
                title: trans('settings.two_factor.turned_off'),
                icon: 'i-lucide-check',
                color: 'success',
            });
        },
        onFinish: () => (turningOff.value = false),
    });
}

async function showRecoveryCodes() {
    loadingRecoveryCodes.value = true;

    try {
        recoveryCodes.value = (await http.get(
            props.routes.recoveryCodes().url,
        )) as string[];
    } catch {
        failed();
    } finally {
        loadingRecoveryCodes.value = false;
    }
}

async function regenerateRecoveryCodes() {
    loadingRecoveryCodes.value = true;

    try {
        await http.post(props.routes.regenerateRecoveryCodes().url);
        await showRecoveryCodes();
        toast.add({
            title: trans('settings.two_factor.recovery_codes_regenerated'),
            icon: 'i-lucide-check',
            color: 'success',
        });
    } catch {
        failed();
    } finally {
        loadingRecoveryCodes.value = false;
    }
}

async function copy(text: string) {
    await navigator.clipboard.writeText(text);
    toast.add({
        title: trans('settings.two_factor.copied'),
        icon: 'i-lucide-check',
        color: 'success',
    });
}
</script>

<template>
    <div class="flex w-full flex-col gap-4 sm:max-w-sm">
        <div class="flex flex-wrap items-center gap-2">
            <UBadge
                :color="enabled ? 'success' : 'neutral'"
                :label="
                    enabled
                        ? trans('settings.two_factor.on')
                        : trans('settings.two_factor.off')
                "
                variant="subtle"
            />

            <UButton
                v-if="!enabled"
                :label="trans('settings.two_factor.turn_on')"
                :loading="turningOn"
                @click="turnOn"
            />

            <template v-else>
                <UButton
                    color="neutral"
                    :label="
                        recoveryCodes
                            ? trans('settings.two_factor.hide_recovery_codes')
                            : trans('settings.two_factor.show_recovery_codes')
                    "
                    :loading="loadingRecoveryCodes && !recoveryCodes"
                    variant="subtle"
                    @click="
                        recoveryCodes
                            ? (recoveryCodes = null)
                            : showRecoveryCodes()
                    "
                />
                <UButton
                    color="error"
                    :label="trans('settings.two_factor.turn_off')"
                    :loading="turningOff"
                    variant="subtle"
                    @click="turnOff"
                />
            </template>
        </div>

        <div
            v-if="enabled && recoveryCodes"
            class="border-default flex flex-col gap-3 rounded-md border p-4"
        >
            <p class="text-muted text-sm">
                {{ trans('settings.two_factor.recovery_codes_description') }}
            </p>

            <ul class="grid grid-cols-2 gap-1 font-mono text-sm">
                <li v-for="recoveryCode in recoveryCodes" :key="recoveryCode">
                    {{ recoveryCode }}
                </li>
            </ul>

            <div class="flex flex-wrap gap-2">
                <UButton
                    color="neutral"
                    icon="i-lucide-copy"
                    :label="trans('settings.two_factor.copy')"
                    size="sm"
                    variant="subtle"
                    @click="copy(recoveryCodes.join('\n'))"
                />
                <UButton
                    color="neutral"
                    icon="i-lucide-refresh-cw"
                    :label="
                        trans('settings.two_factor.regenerate_recovery_codes')
                    "
                    :loading="loadingRecoveryCodes"
                    size="sm"
                    variant="subtle"
                    @click="regenerateRecoveryCodes"
                />
            </div>
        </div>

        <UModal
            v-model:open="setupOpen"
            :description="trans('settings.two_factor.setup_description')"
            :title="trans('settings.two_factor.setup_title')"
        >
            <template #body>
                <form
                    class="flex flex-col items-center gap-6"
                    @submit.prevent="confirm"
                >
                    <img
                        v-if="qrCodeSrc"
                        :alt="trans('settings.two_factor.qr_code')"
                        class="size-48 rounded-md bg-white p-2"
                        :src="qrCodeSrc"
                    />

                    <div
                        v-if="secretKey"
                        class="flex w-full flex-col items-center gap-1"
                    >
                        <span class="text-muted text-sm">
                            {{ trans('settings.two_factor.setup_key') }}
                        </span>
                        <div class="flex items-center gap-1">
                            <code class="break-all font-mono text-sm">
                                {{ secretKey }}
                            </code>
                            <UButton
                                :aria-label="trans('settings.two_factor.copy')"
                                color="neutral"
                                icon="i-lucide-copy"
                                size="xs"
                                variant="ghost"
                                @click="copy(secretKey)"
                            />
                        </div>
                    </div>

                    <UFormField
                        class="flex flex-col items-center"
                        :error="confirmForm.errors.code"
                        :label="trans('settings.two_factor.code')"
                        name="code"
                    >
                        <UPinInput
                            v-model="codeDigits"
                            autofocus
                            :length="6"
                            otp
                            @complete="confirm"
                        />
                    </UFormField>

                    <div class="flex w-full justify-end gap-2">
                        <UButton
                            color="neutral"
                            :label="trans('common.actions.cancel')"
                            variant="subtle"
                            @click="setupOpen = false"
                        />
                        <UButton
                            :disabled="codeDigits.join('').length < 6"
                            :label="trans('settings.two_factor.confirm')"
                            :loading="confirmForm.processing"
                            type="submit"
                        />
                    </div>
                </form>
            </template>
        </UModal>
    </div>
</template>
