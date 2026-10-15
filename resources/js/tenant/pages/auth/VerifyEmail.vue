<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { type VNode } from 'vue';
import EmailVerificationNotificationController from '@/actions/Laravel/Fortify/Http/Controllers/EmailVerificationNotificationController';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { logout } from '@/routes';

const { trans } = useTrans();

defineOptions({
    layout: (h: any, page: VNode) => {
        return h(
            AuthLayout,
            {
                title: useTrans().trans('auth.verify_email.title'),
                description: useTrans().trans('auth.verify_email.description'),
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
        <Head :title="trans('auth.verify_email.head')" />

        <div
            v-if="status === 'verification-link-sent'"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ trans('auth.verify_email.link_sent') }}
        </div>

        <Form
            v-bind="EmailVerificationNotificationController.store.form()"
            class="space-y-6 text-center"
            v-slot="{ processing }"
        >
            <UButton :loading="processing" type="submit">{{
                trans('auth.verify_email.resend')
            }}</UButton>

            <TextLink
                :href="logout()"
                as="button"
                class="text-primary mx-auto block text-sm font-medium"
            >
                {{ trans('auth.verify_email.log_out') }}
            </TextLink>
        </Form>
    </div>
</template>
