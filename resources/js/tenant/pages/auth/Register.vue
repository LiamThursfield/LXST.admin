<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { type VNode } from 'vue';
import RegisteredUserController from '@/actions/Laravel/Fortify/Http/Controllers/RegisteredUserController';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';

defineOptions({
    layout: (h, page: VNode) => {
        return h(
            AuthLayout,
            {
                title: 'Sign Up',
                description: 'Create a new account.',
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
        <Head title="Register" />

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
                    label="First Name"
                >
                    <UInput
                        type="text"
                        class="w-full"
                        autocomplete="first_name"
                        placeholder="First Name"
                        autofocus
                        required
                    />
                </UFormField>

                <UFormField
                    name="last_name"
                    :error="errors.last_name"
                    label="Last Name"
                >
                    <UInput
                        type="text"
                        class="w-full"
                        autocomplete="last_name"
                        placeholder="Last Name"
                        autofocus
                        required
                    />
                </UFormField>

                <UFormField
                    name="email"
                    :error="errors.email"
                    label="Email address"
                >
                    <UInput
                        type="email"
                        class="w-full"
                        autocomplete="email"
                        placeholder="email@example.com"
                        autofocus
                        required
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
                    >Create Account</UButton
                >
            </div>

            <div class="text-muted text-center text-sm">
                Already have an account?
                <TextLink :href="login()" :tabindex="5" class="text-primary"
                    >Log In</TextLink
                >
            </div>
        </Form>
    </div>
</template>
