<script setup lang="ts">
import type { CaptchaWidgetData } from '@/types';

/**
 * The tenant's captcha (#74), rendered from what the public API gives: Cloudflare Turnstile, loaded
 * from Cloudflare when first shown. The token it gives is bound with v-model, and is cleared when it
 * expires or fails. Call reset() after a submission, as each token can only be checked once.
 */
const props = defineProps<{
    captcha: CaptchaWidgetData;
}>();

const token = defineModel<string>({ default: '' });

const { trans } = useTrans();

type Turnstile = {
    render: (
        element: HTMLElement,
        options: {
            sitekey: string;
            theme?: 'auto' | 'light' | 'dark';
            callback: (token: string) => void;
            'expired-callback': () => void;
            'error-callback': () => void;
        },
    ) => string;
    reset: (widgetId: string) => void;
    remove: (widgetId: string) => void;
};

const SCRIPT_URL =
    'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

const turnstile = () =>
    (window as unknown as { turnstile?: Turnstile }).turnstile;

let scriptLoading: Promise<void> | null = null;

/** Adds Turnstile's script once, however many widgets the page has. */
function loadTurnstile(): Promise<void> {
    if (turnstile()) {
        return Promise.resolve();
    }

    scriptLoading ??= new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = SCRIPT_URL;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => {
            scriptLoading = null;
            reject(new Error('Turnstile could not be loaded.'));
        };
        document.head.appendChild(script);
    });

    return scriptLoading;
}

const container = ref<HTMLElement | null>(null);
const failed = ref(false);
let widgetId: string | null = null;

onMounted(async () => {
    if (props.captcha.provider !== 'turnstile') {
        return;
    }

    try {
        await loadTurnstile();
    } catch {
        failed.value = true;

        return;
    }

    if (!container.value) {
        return;
    }

    widgetId =
        turnstile()?.render(container.value, {
            sitekey: props.captcha.site_key,
            theme: 'auto',
            callback: (value) => (token.value = value),
            'expired-callback': () => (token.value = ''),
            'error-callback': () => (token.value = ''),
        }) ?? null;
});

onBeforeUnmount(() => {
    if (widgetId) {
        turnstile()?.remove(widgetId);
    }
});

function reset() {
    token.value = '';

    if (widgetId) {
        turnstile()?.reset(widgetId);
    }
}

defineExpose({ reset });
</script>

<template>
    <div>
        <div ref="container" />
        <p v-if="failed" class="text-error text-sm">
            {{ trans('common.captcha.load_failed') }}
        </p>
    </div>
</template>
