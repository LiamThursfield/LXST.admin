<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import AuthenticatedSessionController from '@/actions/Laravel/Fortify/Http/Controllers/AuthenticatedSessionController';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { register } from '@/routes';
import { request } from '@/routes/password';

const { trans } = useTrans();

defineOptions({
    layout: (h: any, page: any) => {
        return h(
            AuthLayout,
            {
                title: useTrans().trans('auth.login.title'),
                description: useTrans().trans('auth.login.description'),
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

const inputEmail = ref('');
</script>

<template>
    <div>
        <Head :title="trans('auth.login.head')" />

        <div
            v-if="status"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <Form
            v-bind="AuthenticatedSessionController.store.form()"
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
                        v-model="inputEmail"
                    />
                </UFormField>

                <UFormField
                    name="password"
                    :error="errors.password"
                    :label="trans('auth.fields.password')"
                >
                    <UInput
                        type="password"
                        class="w-full"
                        autocomplete="current-password"
                        :placeholder="trans('auth.fields.password')"
                        required
                    />
                    <template #hint>
                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            class="text-primary text-sm"
                            :tabindex="5"
                        >
                            {{ trans('auth.login.forgot_password') }}
                        </TextLink>
                    </template>
                </UFormField>

                <UFormField name="remember" :error="errors.password">
                    <UCheckbox :label="trans('auth.fields.remember_me')" />
                </UFormField>

                <UButton
                    :loading="processing"
                    type="submit"
                    block
                    class="mt-4"
                    >{{ trans('auth.login.submit') }}</UButton
                >
            </div>

            <div v-if="canRegister" class="text-muted text-center text-sm">
                {{ trans('auth.login.no_account') }}
                <TextLink
                    :href="register()"
                    :tabindex="5"
                    class="text-primary"
                    >{{ trans('auth.login.sign_up') }}</TextLink
                >
            </div>
        </Form>
    </div>
</template>
