<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import ProfileController from '@/actions/App/Http/Controllers/Tenant/Admin/Settings/ProfileController';
import SettingsSubLayout from '@/sublayouts/admin/SettingsSubLayout.vue';
import PasswordController from '../../../../actions/App/Http/Controllers/Tenant/Admin/Settings/PasswordController';

const { trans } = useTrans();

const toast = useToast();

function handleSuccess() {
    toast.add({
        title: trans('settings.success'),
        description: trans('settings.security.password_updated'),
        icon: 'i-lucide-check',
        color: 'success',
    });
}
</script>

<template>
    <SettingsSubLayout>
        <Head :title="trans('settings.security.head')" />

        <UPageCard
            :title="trans('settings.security.password_title')"
            :description="trans('settings.security.password_description')"
            variant="subtle"
        >
            <Form
                v-bind="PasswordController.update.form()"
                :options="{
                    preserveScroll: true,
                }"
                reset-on-success
                :reset-on-error="[
                    'password',
                    'password_confirmation',
                    'current_password',
                ]"
                class="flex max-w-xs flex-col gap-4"
                v-slot="{ errors, processing }"
                @success="handleSuccess"
            >
                <UFormField
                    name="current_password"
                    :error="errors.current_password"
                >
                    <UInput
                        type="password"
                        :placeholder="
                            trans('settings.security.current_password')
                        "
                        class="w-full"
                    />
                </UFormField>

                <UFormField name="password" :error="errors.password">
                    <UInput
                        type="password"
                        :placeholder="trans('settings.security.new_password')"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    name="password_confirmation"
                    :error="errors.password_confirmation"
                >
                    <UInput
                        type="password"
                        :placeholder="
                            trans('settings.security.confirm_password')
                        "
                        class="w-full"
                    />
                </UFormField>

                <UButton
                    :label="trans('settings.security.update')"
                    class="w-fit"
                    type="submit"
                    :disabled="processing"
                />
            </Form>
        </UPageCard>

        <UPageCard
            :title="trans('settings.security.account_title')"
            :description="trans('settings.security.account_description')"
            class="from-error/10 to-default bg-gradient-to-tl from-5%"
        >
            <template #footer>
                <DeleteAccount :controller="ProfileController" />
            </template>
        </UPageCard>
    </SettingsSubLayout>
</template>

<style scoped></style>
