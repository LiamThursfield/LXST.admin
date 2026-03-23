<script lang="ts" setup>
import { Form, Head } from '@inertiajs/vue3';
import { type VNode } from 'vue';
import ConfirmablePasswordController from '@/actions/App/Http/Controllers/Central/Fortify/ConfirmablePasswordController';
import AuthLayout from '@/layouts/AuthLayout.vue';

defineOptions({
    layout: (h, page: VNode) => {
        return h(
            AuthLayout,
            {
                title: 'Confirm your password',
                description:
                    'This is a secure area of the application. Please confirm your password before continuing.',
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
        <Head title="Verify email" />

        <div
            v-if="status === 'verification-link-sent'"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            A new verification link has been sent to the email address you
            provided during registration.
        </div>

        <Form
            reset-on-success
            class="space-y-6 text-center"
            v-slot="{ errors, processing }"
            v-bind="ConfirmablePasswordController.store.form()"
        >
            <UFormField
                name="password"
                :error="errors.password"
                label="Password"
            >
                <UInput
                    type="password"
                    class="w-full"
                    autocomplete="current-password"
                    autofocus
                    required
                />
            </UFormField>

            <div class="flex items-center">
                <UButton :loading="processing" type="submit" block
                    >Confirm Password</UButton
                >
            </div>
        </Form>
    </div>
</template>
