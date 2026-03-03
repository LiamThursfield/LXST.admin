<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { type VNode } from 'vue';
import NewPasswordController from '@/actions/Laravel/Fortify/Http/Controllers/NewPasswordController';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';

defineOptions({
    layout: (h, page: VNode) => {
        return h(
            AuthLayout,
            {
                title: 'Reset Password',
                description: 'Please enter your new password below.',
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
        <Head title="Reset Password" />

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
                    label="Email address"
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
                    label="Password"
                >
                    <UInput
                        type="password"
                        class="w-full"
                        autocomplete="current-password"
                        placeholder="Password"
                        min="8"
                        required
                    />
                </UFormField>

                <UFormField
                    name="password_confirmation"
                    :error="errors.password"
                    label="Confirm Password"
                >
                    <UInput
                        type="password"
                        class="w-full"
                        autocomplete="confirm-password"
                        placeholder="Confirm Password"
                        min="8"
                        required
                    />
                </UFormField>

                <UButton :loading="processing" type="submit" block class="mt-4"
                    >Reset Password</UButton
                >
            </div>

            <div class="text-muted text-center text-sm">
                Or, return to
                <TextLink :href="login()" :tabindex="5" class="text-primary"
                    >Log In</TextLink
                >
            </div>
        </Form>
    </div>
</template>
