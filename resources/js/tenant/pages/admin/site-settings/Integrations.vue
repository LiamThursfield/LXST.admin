<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import * as z from 'zod';
import { update } from '@/actions/App/Http/Controllers/Tenant/Admin/SiteSettings/IntegrationSettingsController';
import SiteSettingsSubLayout from '@/sublayouts/admin/SiteSettingsSubLayout.vue';
import type { SelectItem } from '@/types';

const props = defineProps<{
    /** The saved settings, without the captcha secret key. */
    settings: {
        gtm_id: string | null;
        captcha_provider: string | null;
        captcha_site_key: string | null;
    };
    /** Whether a captcha secret key is saved. It's never sent to the browser. */
    hasCaptchaSecret: boolean;
    captchaProviders: SelectItem[];
    /** False for users who can see the settings but not change them. */
    canManage: boolean;
}>();

const { trans } = useTrans();

/** Radio groups can't hold null, so no captcha provider is stored as 'none'. */
const NONE = 'none';

const schema = z.object({
    gtm_id: z
        .string()
        .trim()
        .regex(
            /^(GTM-[A-Z0-9]+)?$/i,
            trans('site_settings.integrations.validation.gtm_id_format'),
        )
        .nullable(),
});

const form = useForm({
    gtm_id: props.settings.gtm_id ?? '',
    captcha_provider: props.settings.captcha_provider ?? NONE,
    captcha_site_key: props.settings.captcha_site_key ?? '',
    captcha_secret_key: '',
}).transform((data) => ({
    ...data,
    captcha_provider:
        data.captcha_provider === NONE ? null : data.captcha_provider,
}));

const providerItems = computed(() => [
    { value: NONE, label: trans('site_settings.integrations.captcha_none') },
    ...props.captchaProviders,
]);

function discard() {
    form.reset();
    form.clearErrors();
}

function onSubmit() {
    if (!props.canManage) return;

    // Show what was saved, which the server may have tidied (e.g. trimmed or upper-cased). The
    // secret key is never sent back, so clear what was typed.
    form.submit(update(), {
        preserveScroll: true,
        onSuccess: () => {
            form.gtm_id = props.settings.gtm_id ?? '';
            form.captcha_provider = props.settings.captcha_provider ?? NONE;
            form.captcha_site_key = props.settings.captcha_site_key ?? '';
            form.captcha_secret_key = '';
            form.defaults();
        },
    });
}
</script>

<template>
    <SiteSettingsSubLayout>
        <SettingsPage
            :title="trans('site_settings.integrations.title')"
            :description="trans('site_settings.integrations.description')"
            :readonly="!canManage"
            form-id="integration-settings"
            :is-dirty="form.isDirty"
            :processing="form.processing"
            @discard="discard"
        >
            <UForm
                id="integration-settings"
                :schema="schema"
                :state="form"
                :disabled="!canManage"
                @submit="onSubmit"
            >
                <SettingsRow
                    :label="trans('site_settings.integrations.gtm_id')"
                    :description="
                        trans('site_settings.integrations.gtm_id_help')
                    "
                >
                    <UFormField name="gtm_id" :error="form.errors.gtm_id">
                        <UInput
                            v-model="form.gtm_id"
                            :aria-label="
                                trans('site_settings.integrations.gtm_id')
                            "
                            class="w-full sm:w-80"
                            placeholder="GTM-ABC1234"
                        />
                    </UFormField>
                </SettingsRow>

                <SettingsRow
                    :label="trans('site_settings.integrations.captcha')"
                    :description="
                        trans('site_settings.integrations.captcha_help')
                    "
                >
                    <div class="flex flex-col gap-4">
                        <UFormField
                            name="captcha_provider"
                            :error="form.errors.captcha_provider"
                        >
                            <URadioGroup
                                v-model="form.captcha_provider"
                                :items="providerItems"
                                :disabled="!canManage"
                            />
                        </UFormField>

                        <!-- The chosen provider's keys, nested under it so they read as part of the captcha -->
                        <div
                            v-if="form.captcha_provider !== NONE"
                            class="border-primary flex flex-col gap-4 border-l-2 pl-4"
                        >
                            <p class="text-muted text-sm">
                                {{
                                    trans(
                                        'site_settings.integrations.captcha_turnstile_help',
                                    )
                                }}
                            </p>

                            <UFormField
                                name="captcha_site_key"
                                :label="
                                    trans(
                                        'site_settings.integrations.captcha_site_key',
                                    )
                                "
                                :error="form.errors.captcha_site_key"
                            >
                                <UInput
                                    v-model="form.captcha_site_key"
                                    class="w-full"
                                />
                            </UFormField>

                            <UFormField
                                name="captcha_secret_key"
                                :label="
                                    trans(
                                        'site_settings.integrations.captcha_secret_key',
                                    )
                                "
                                :help="
                                    hasCaptchaSecret
                                        ? trans(
                                              'site_settings.integrations.captcha_secret_saved',
                                          )
                                        : undefined
                                "
                                :error="form.errors.captcha_secret_key"
                            >
                                <UInput
                                    v-model="form.captcha_secret_key"
                                    type="password"
                                    autocomplete="new-password"
                                    :placeholder="
                                        hasCaptchaSecret
                                            ? '••••••••'
                                            : undefined
                                    "
                                    class="w-full"
                                />
                            </UFormField>
                        </div>
                    </div>
                </SettingsRow>
            </UForm>
        </SettingsPage>
    </SiteSettingsSubLayout>
</template>
