<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    test,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/SiteSettings/MessagingSettingsController';
import SiteSettingsSubLayout from '@/sublayouts/admin/SiteSettingsSubLayout.vue';

type MessagingSettings = {
    provider: 'twilio' | null;
    twilio_account_sid: string | null;
    sms_from: string | null;
    whatsapp_from: string | null;
};

const props = defineProps<{
    /** The saved settings, without the token. */
    settings: MessagingSettings;
    /** Whether a token is saved. It's never sent to the browser. */
    hasToken: boolean;
    /** False for users who can see the settings but not change them. */
    canManage: boolean;
}>();

const { trans } = useTrans();

/** USelect-style radios can't hold null, so no provider is stored as 'none'. */
const NONE = 'none';

const form = useForm({
    provider: (props.settings.provider ?? NONE) as 'twilio' | typeof NONE,
    twilio_account_sid: props.settings.twilio_account_sid ?? '',
    twilio_auth_token: '',
    sms_from: props.settings.sms_from ?? '',
    whatsapp_from: props.settings.whatsapp_from ?? '',
}).transform((data) => ({
    ...data,
    provider: data.provider === NONE ? null : data.provider,
}));

const providerItems = computed(() => [
    { value: NONE, label: trans('site_settings.messaging.provider_none') },
    {
        value: 'twilio',
        label: trans('site_settings.messaging.provider_twilio'),
    },
]);

function discard() {
    form.reset();
    form.clearErrors();
}

function onSubmit() {
    if (!props.canManage) return;

    form.submit(update(), {
        preserveScroll: true,
        onSuccess: () => {
            // The token is never sent back, so clear what was typed, and treat the rest as saved
            form.twilio_auth_token = '';
            form.defaults();
        },
    });
}

const testForm = useForm({ to: '' });
const testOpen = ref(false);

function sendTest() {
    testForm.submit(test(), {
        preserveScroll: true,
        onSuccess: () => (testOpen.value = false),
    });
}
</script>

<template>
    <SiteSettingsSubLayout>
        <SettingsPage
            :title="trans('site_settings.messaging.title')"
            :description="trans('site_settings.messaging.description')"
            :readonly="!canManage"
            form-id="messaging-settings"
            :is-dirty="form.isDirty"
            :processing="form.processing"
            @discard="discard"
        >
            <template v-if="canManage" #actions>
                <UButton
                    color="neutral"
                    variant="outline"
                    icon="i-lucide-send"
                    :label="trans('site_settings.messaging.test')"
                    @click="testOpen = true"
                />
            </template>

            <form id="messaging-settings" @submit.prevent="onSubmit">
                <SettingsRow :label="trans('site_settings.messaging.provider')">
                    <UFormField :error="form.errors.provider">
                        <URadioGroup
                            v-model="form.provider"
                            :items="providerItems"
                            :disabled="!canManage"
                        />
                    </UFormField>
                </SettingsRow>

                <template v-if="form.provider === 'twilio'">
                    <SettingsRow
                        :label="trans('site_settings.messaging.account_sid')"
                    >
                        <UFormField :error="form.errors.twilio_account_sid">
                            <UInput
                                v-model="form.twilio_account_sid"
                                :disabled="!canManage"
                                class="w-full"
                            />
                        </UFormField>
                    </SettingsRow>

                    <SettingsRow
                        :label="trans('site_settings.messaging.auth_token')"
                        :description="
                            hasToken
                                ? trans('site_settings.messaging.token_saved')
                                : undefined
                        "
                    >
                        <UFormField :error="form.errors.twilio_auth_token">
                            <UInput
                                v-model="form.twilio_auth_token"
                                type="password"
                                autocomplete="new-password"
                                :placeholder="hasToken ? '••••••••' : undefined"
                                :disabled="!canManage"
                                class="w-full"
                            />
                        </UFormField>
                    </SettingsRow>

                    <SettingsRow
                        :label="trans('site_settings.messaging.sms_from')"
                        :description="
                            trans('site_settings.messaging.sms_from_help')
                        "
                    >
                        <UFormField :error="form.errors.sms_from">
                            <UInput
                                v-model="form.sms_from"
                                :disabled="!canManage"
                                class="w-full"
                            />
                        </UFormField>
                    </SettingsRow>

                    <SettingsRow
                        :label="trans('site_settings.messaging.whatsapp_from')"
                        :description="
                            trans('site_settings.messaging.whatsapp_from_help')
                        "
                    >
                        <UFormField :error="form.errors.whatsapp_from">
                            <UInput
                                v-model="form.whatsapp_from"
                                :disabled="!canManage"
                                class="w-full"
                            />
                        </UFormField>
                    </SettingsRow>
                </template>
            </form>
        </SettingsPage>

        <UModal
            v-model:open="testOpen"
            :title="trans('site_settings.messaging.test')"
            :description="
                form.isDirty
                    ? trans('site_settings.messaging.test_save_first')
                    : trans('site_settings.messaging.test_description')
            "
        >
            <template #body>
                <form id="messaging-test" @submit.prevent="sendTest">
                    <UFormField
                        :label="trans('site_settings.messaging.test_to')"
                        :error="testForm.errors.to"
                    >
                        <UInput
                            v-model="testForm.to"
                            type="tel"
                            placeholder="+447700900123"
                            class="w-full"
                            :disabled="form.isDirty"
                        />
                    </UFormField>
                </form>
            </template>
            <template #footer>
                <div class="flex w-full justify-end gap-2">
                    <UButton
                        color="neutral"
                        variant="ghost"
                        :label="trans('common.actions.cancel')"
                        @click="testOpen = false"
                    />
                    <UButton
                        type="submit"
                        form="messaging-test"
                        icon="i-lucide-send"
                        :disabled="form.isDirty"
                        :loading="testForm.processing"
                        :label="trans('site_settings.messaging.test_send')"
                    />
                </div>
            </template>
        </UModal>
    </SiteSettingsSubLayout>
</template>
