<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import ProfileController from '@/actions/App/Http/Controllers/Tenant/Admin/Settings/ProfileController';
import ConfirmedTwoFactorAuthenticationController from '@/actions/Laravel/Fortify/Http/Controllers/ConfirmedTwoFactorAuthenticationController';
import RecoveryCodeController from '@/actions/Laravel/Fortify/Http/Controllers/RecoveryCodeController';
import TwoFactorAuthenticationController from '@/actions/Laravel/Fortify/Http/Controllers/TwoFactorAuthenticationController';
import TwoFactorQrCodeController from '@/actions/Laravel/Fortify/Http/Controllers/TwoFactorQrCodeController';
import TwoFactorSecretKeyController from '@/actions/Laravel/Fortify/Http/Controllers/TwoFactorSecretKeyController';
import SettingsSubLayout from '@/sublayouts/admin/SettingsSubLayout.vue';
import PasswordController from '../../../../actions/App/Http/Controllers/Tenant/Admin/Settings/PasswordController';

defineProps<{
    twoFactorEnabled: boolean;
}>();

const twoFactorRoutes = {
    enable: TwoFactorAuthenticationController.store,
    confirm: ConfirmedTwoFactorAuthenticationController.store,
    disable: TwoFactorAuthenticationController.destroy,
    qrCode: TwoFactorQrCodeController.show,
    secretKey: TwoFactorSecretKeyController.show,
    recoveryCodes: RecoveryCodeController.index,
    regenerateRecoveryCodes: RecoveryCodeController.store,
};

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
        <SettingsPage
            :title="trans('settings.security.title')"
            :description="trans('settings.security.description')"
        >
            <SettingsRow
                :label="trans('settings.security.password_title')"
                :description="trans('settings.security.password_description')"
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
                    class="flex w-full flex-col gap-4 sm:max-w-sm"
                    v-slot="{ errors, processing }"
                    @success="handleSuccess"
                >
                    <UFormField
                        :label="trans('settings.security.current_password')"
                        name="current_password"
                        :error="errors.current_password"
                    >
                        <UInput
                            type="password"
                            autocomplete="current-password"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        :label="trans('settings.security.new_password')"
                        name="password"
                        :error="errors.password"
                    >
                        <UInput
                            type="password"
                            autocomplete="new-password"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        :label="trans('settings.security.confirm_password')"
                        name="password_confirmation"
                        :error="errors.password_confirmation"
                    >
                        <UInput
                            type="password"
                            autocomplete="new-password"
                            class="w-full"
                        />
                    </UFormField>

                    <UButton
                        :label="trans('settings.security.update')"
                        class="w-fit"
                        type="submit"
                        :loading="processing"
                    />
                </Form>
            </SettingsRow>

            <SettingsRow
                :label="trans('settings.two_factor.title')"
                :description="trans('settings.two_factor.description')"
            >
                <TwoFactorAuthentication
                    :enabled="twoFactorEnabled"
                    :routes="twoFactorRoutes"
                />
            </SettingsRow>

            <SettingsRow
                :label="trans('settings.security.account_title')"
                :description="trans('settings.security.account_description')"
            >
                <div>
                    <DeleteAccount :controller="ProfileController" />
                </div>
            </SettingsRow>
        </SettingsPage>
    </SettingsSubLayout>
</template>
