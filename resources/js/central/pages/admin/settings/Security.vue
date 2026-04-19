<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import PasswordController from '@/actions/App/Http/Controllers/Central/Admin/Settings/PasswordController';
import ProfileController from '@/actions/App/Http/Controllers/Central/Admin/Settings/ProfileController';
import SettingsSubLayout from '@/sublayouts/admin/SettingsSubLayout.vue';

const toast = useToast();

function handleSuccess() {
    toast.add({
        title: 'Success',
        description: 'Your password has been updated.',
        icon: 'i-lucide-check',
        color: 'success',
    });
}
</script>

<template>
    <SettingsSubLayout>
        <Head title="Security settings" />

        <UPageCard
            title="Password"
            description="Confirm your current password before setting a new one."
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
                        placeholder="Current password"
                        class="w-full"
                    />
                </UFormField>

                <UFormField name="password" :error="errors.password">
                    <UInput
                        type="password"
                        placeholder="New password"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    name="password_confirmation"
                    :error="errors.password_confirmation"
                >
                    <UInput
                        type="password"
                        placeholder="Confirm password"
                        class="w-full"
                    />
                </UFormField>

                <UButton
                    label="Update"
                    class="w-fit"
                    type="submit"
                    :disabled="processing"
                />
            </Form>
        </UPageCard>

        <UPageCard
            title="Account"
            description="No longer want to use our service? You can delete your account here. This action is not reversible. All information related to this account will be deleted permanently."
            class="from-error/10 to-default bg-gradient-to-tl from-5%"
        >
            <template #footer>
                <DeleteAccount :controller="ProfileController" />
            </template>
        </UPageCard>
    </SettingsSubLayout>
</template>

<style scoped></style>
