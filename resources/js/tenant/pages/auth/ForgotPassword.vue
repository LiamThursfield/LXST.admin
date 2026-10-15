<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { type VNode } from 'vue';
import PasswordResetLinkController from '@/actions/Laravel/Fortify/Http/Controllers/PasswordResetLinkController';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';

const { trans } = useTrans();

defineOptions({
    layout: (h: any, page: VNode) => {
        return h(
            AuthLayout,
            {
                title: useTrans().trans('auth.forgot_password.title'),
                description: useTrans().trans(
                    'auth.forgot_password.description',
                ),
            },
            () => page,
        );
    },
});

defineProps<{
    canResetPassword?: boolean;
    canRegister?: boolean;
    status?: string;
}>();
</script>

<template>
    <div>
        <Head :title="trans('auth.forgot_password.head')" />

        <div
            v-if="status"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <Form
            v-bind="PasswordResetLinkController.store.form()"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <UFormField
                    name="email"
                    :error="errors.email"
                    :label="trans('auth.fields.email')"
                >
                    <UInput
                        type="email"
                        class="w-full"
                        autocomplete="email"
                        :placeholder="trans('auth.fields.email_placeholder')"
                        autofocus
                        required
                    />
                </UFormField>

                <UButton
                    :loading="processing"
                    type="submit"
                    block
                    class="mt-4"
                    >{{ trans('auth.forgot_password.submit') }}</UButton
                >
            </div>

            <div class="text-muted text-center text-sm">
                {{ trans('auth.forgot_password.return_to') }}
                <TextLink :href="login()" :tabindex="5" class="text-primary">{{
                    trans('auth.forgot_password.log_in')
                }}</TextLink>
            </div>
        </Form>
    </div>
</template>
