<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { type VNode } from 'vue';
import EmailVerificationNotificationController from '@/actions/App/Http/Controllers/Central/Fortify/EmailVerificationNotificationController';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { logout } from '@/routes';

defineOptions({
    layout: (h: any, page: VNode) => {
        return h(
            AuthLayout,
            {
                title: 'Verify email',
                description:
                    'Please verify your email address by clicking on the link we just emailed to you.',
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
            v-bind="EmailVerificationNotificationController.store.form()"
            class="space-y-6 text-center"
            v-slot="{ processing }"
        >
            <UButton :loading="processing" type="submit"
                >Resend verification email</UButton
            >

            <TextLink
                :href="logout()"
                as="button"
                class="text-primary mx-auto block text-sm font-medium"
            >
                Log out
            </TextLink>
        </Form>
    </div>
</template>
