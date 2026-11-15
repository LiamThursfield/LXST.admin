<script lang="ts" setup>
import type { RouteDefinition } from '@/wayfinder';

/**
 * The second step of logging in: a code from the authenticator app, or a recovery code
 */
const props = defineProps<{
    action: () => RouteDefinition<'post'>;
}>();

const { trans } = useTrans();

const usingRecoveryCode = ref(false);
const codeDigits = ref<string[]>([]);
const form = useForm({ code: '', recovery_code: '' });

function toggleRecoveryCode() {
    usingRecoveryCode.value = !usingRecoveryCode.value;
    codeDigits.value = [];
    form.reset();
    form.clearErrors();
}

function submit() {
    form.code = usingRecoveryCode.value ? '' : codeDigits.value.join('');

    form.transform((data) =>
        usingRecoveryCode.value
            ? { recovery_code: data.recovery_code }
            : { code: data.code },
    ).submit(props.action(), {
        onError: () => (codeDigits.value = []),
    });
}
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <p class="text-muted text-center text-sm">
            {{
                usingRecoveryCode
                    ? trans('auth.two_factor_challenge.recovery_description')
                    : trans('auth.two_factor_challenge.code_description')
            }}
        </p>

        <UFormField
            v-if="usingRecoveryCode"
            :error="form.errors.recovery_code"
            :label="trans('auth.two_factor_challenge.recovery_code')"
            name="recovery_code"
        >
            <UInput
                v-model="form.recovery_code"
                autocomplete="one-time-code"
                autofocus
                class="w-full"
                required
            />
        </UFormField>

        <UFormField
            v-else
            class="flex flex-col items-center"
            :error="form.errors.code"
            :label="trans('auth.two_factor_challenge.code')"
            name="code"
        >
            <UPinInput
                v-model="codeDigits"
                autofocus
                :length="6"
                otp
                @complete="submit"
            />
        </UFormField>

        <UButton
            block
            :label="trans('auth.two_factor_challenge.submit')"
            :loading="form.processing"
            type="submit"
        />

        <button
            class="text-primary text-center text-sm hover:underline"
            type="button"
            @click="toggleRecoveryCode"
        >
            {{
                usingRecoveryCode
                    ? trans('auth.two_factor_challenge.use_code')
                    : trans('auth.two_factor_challenge.use_recovery_code')
            }}
        </button>
    </form>
</template>
