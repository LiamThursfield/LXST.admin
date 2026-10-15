<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { type VNode } from 'vue';
import NewPasswordController from '@/actions/App/Http/Controllers/Central/Fortify/NewPasswordController';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';

const { trans } = useTrans();

defineOptions({
    layout: (h: any, page: VNode) => {
        return h(
            AuthLayout,
            {
                title: useTrans().trans('auth.reset_password.title'),
                description: useTrans().trans(
                    'auth.reset_password.description',
                ),
            },
            () => page,
        );
    },
});

const props = defineProps<{
    token: string;
    email: string;
}>();

const inputEmail = ref(props.email);
</script>

<template>
    <div>
        <Head :title="trans('auth.reset_password.head')" />

        <Form
            v-bind="NewPasswordController.store.form()"
            :transform="(data) => ({ ...data, token, email })"
            :reset-on-success="['password', 'password_confirmation']"
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
                        readonly
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
                        min="8"
                        required
                    />
                </UFormField>

                <UFormField
                    name="password_confirmation"
                    :error="errors.password"
                    :label="trans('auth.fields.confirm_password')"
                >
                    <UInput
                        type="password"
                        class="w-full"
                        autocomplete="confirm-password"
                        :placeholder="trans('auth.fields.confirm_password')"
                        min="8"
                        required
                    />
                </UFormField>

                <UButton
                    :loading="processing"
                    type="submit"
                    block
                    class="mt-4"
                    >{{ trans('auth.reset_password.submit') }}</UButton
                >
            </div>

            <div class="text-muted text-center text-sm">
                {{ trans('auth.reset_password.return_to') }}
                <TextLink :href="login()" :tabindex="5" class="text-primary">{{
                    trans('auth.reset_password.log_in')
                }}</TextLink>
            </div>
        </Form>
    </div>
</template>
