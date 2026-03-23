<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { type VNode } from 'vue';
import PasswordResetLinkController from '@/actions/App/Http/Controllers/Central/Fortify/PasswordResetLinkController';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';

defineOptions({
    layout: (h, page: VNode) => {
        return h(
            AuthLayout,
            {
                title: 'Forgot Password',
                description: 'Request a password reset link.',
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
        <Head title="Forgot Password" />

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

                <UButton :loading="processing" type="submit" block class="mt-4"
                    >Reset Password</UButton
                >
            </div>

            <div class="text-muted text-center text-sm">
                Or, return to
                <TextLink :href="login()" :tabindex="5" class="text-primary"
                    >log in</TextLink
                >
            </div>
        </Form>
    </div>
</template>
