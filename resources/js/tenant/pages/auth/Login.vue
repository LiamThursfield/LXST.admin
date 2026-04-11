<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { type VNode } from 'vue';
import AuthenticatedSessionController from '@/actions/Laravel/Fortify/Http/Controllers/AuthenticatedSessionController';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { register } from '@/routes';
import { request } from '@/routes/password';

defineOptions({
    layout: (h: any, page: VNode) => {
        return h(
            AuthLayout,
            {
                title: 'Sign In',
                description: 'Welcome back to your account.',
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
        <Head title="Sign in" />

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
                    label="Email address"
                >
                    <UInput
                        type="email"
                        class="w-full"
                        autocomplete="email"
                        placeholder="email@example.com"
                        autofocus
                        required
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
                        required
                    />
                    <template #hint>
                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            class="text-primary text-sm"
                            :tabindex="5"
                        >
                            Forgot password?
                        </TextLink>
                    </template>
                </UFormField>

                <UFormField name="remember" :error="errors.password">
                    <UCheckbox label="Remember me" />
                </UFormField>

                <UButton :loading="processing" type="submit" block class="mt-4"
                    >Log in</UButton
                >
            </div>

            <div v-if="canRegister" class="text-muted text-center text-sm">
                Don't have an account?
                <TextLink :href="register()" :tabindex="5" class="text-primary"
                    >Sign up</TextLink
                >
            </div>
        </Form>
    </div>
</template>
