<script lang="ts" setup>
import { Form, Head } from '@inertiajs/vue3';
import { type VNode } from 'vue';
import ConfirmablePasswordController from '@/actions/Laravel/Fortify/Http/Controllers/ConfirmablePasswordController';
import AuthLayout from '@/layouts/AuthLayout.vue';

const { trans } = useTrans();

defineOptions({
    layout: (h: any, page: VNode) => {
        return h(
            AuthLayout,
            {
                title: useTrans().trans('auth.confirm_password.title'),
                description: useTrans().trans(
                    'auth.confirm_password.description',
                ),
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
        <Head :title="trans('auth.confirm_password.head')" />

        <div
            v-if="status === 'verification-link-sent'"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ trans('auth.verify_email.link_sent') }}
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
                :label="trans('auth.fields.password')"
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
                <UButton :loading="processing" type="submit" block>{{
                    trans('auth.confirm_password.submit')
                }}</UButton>
            </div>
        </Form>
    </div>
</template>
