<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { type VNode } from 'vue';
import RegisteredUserController from '@/actions/Laravel/Fortify/Http/Controllers/RegisteredUserController';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';

const { trans } = useTrans();

defineOptions({
    layout: (h: any, page: VNode) => {
        return h(
            AuthLayout,
            {
                title: useTrans().trans('auth.register.title'),
                description: useTrans().trans('auth.register.description'),
            },
            () => page,
        );
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <div>
        <Head :title="trans('auth.register.head')" />

        <div
            v-if="status"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <Form
            v-bind="RegisteredUserController.store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <UFormField
                    name="first_name"
                    :error="errors.first_name"
                    :label="trans('auth.fields.first_name')"
                >
                    <UInput
                        type="text"
                        class="w-full"
                        autocomplete="first_name"
                        :placeholder="trans('auth.fields.first_name')"
                        autofocus
                        required
                    />
                </UFormField>

                <UFormField
                    name="last_name"
                    :error="errors.last_name"
                    :label="trans('auth.fields.last_name')"
                >
                    <UInput
                        type="text"
                        class="w-full"
                        autocomplete="last_name"
                        :placeholder="trans('auth.fields.last_name')"
                        required
                    />
                </UFormField>

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
                        required
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
                    >{{ trans('auth.register.submit') }}</UButton
                >
            </div>

            <div class="text-muted text-center text-sm">
                {{ trans('auth.register.have_account') }}
                <TextLink :href="login()" :tabindex="5" class="text-primary">{{
                    trans('auth.register.log_in')
                }}</TextLink>
            </div>
        </Form>
    </div>
</template>
