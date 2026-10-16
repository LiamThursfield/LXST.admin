<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    test,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/SiteSettings/MailSettingsController';
import SiteSettingsSubLayout from '@/sublayouts/admin/SiteSettingsSubLayout.vue';

type Driver = 'smtp' | 'mailgun' | 'ses';
type Secret = 'smtp_password' | 'mailgun_secret' | 'ses_secret';

type MailSettings = {
    driver: Driver | null;
    from_address: string | null;
    from_name: string | null;
    smtp_host: string | null;
    smtp_port: number | null;
    smtp_username: string | null;
    mailgun_domain: string | null;
    mailgun_endpoint: string | null;
    ses_key: string | null;
    ses_region: string | null;
};

const props = defineProps<{
    /** The saved settings, without secrets. */
    settings: MailSettings;
    /** Whether each secret is saved. Secrets themselves are never sent to the browser. */
    savedSecrets: Record<Secret, boolean>;
    drivers: Driver[];
    mailgunEndpoints: string[];
    /** The platform's sender, used when no from address or name is given. */
    defaultFrom: { address: string | null; name: string | null };
    /** False for users who can see the settings but not change them. */
    canManage: boolean;
}>();

const { trans } = useTrans();
const auth = useAuth();

/** USelect can't hold null, so "the platform's default mailer" is stored as 'default'. */
const DEFAULT_DRIVER = 'default';

const form = useForm({
    driver: (props.settings.driver ?? DEFAULT_DRIVER) as
        Driver | typeof DEFAULT_DRIVER,
    from_address: props.settings.from_address ?? '',
    from_name: props.settings.from_name ?? '',
    smtp_host: props.settings.smtp_host ?? '',
    smtp_port: props.settings.smtp_port ?? 587,
    smtp_username: props.settings.smtp_username ?? '',
    smtp_password: '',
    mailgun_domain: props.settings.mailgun_domain ?? '',
    mailgun_secret: '',
    mailgun_endpoint: props.settings.mailgun_endpoint ?? 'api.mailgun.net',
    ses_key: props.settings.ses_key ?? '',
    ses_secret: '',
    ses_region: props.settings.ses_region ?? '',
}).transform((data) => ({
    ...data,
    driver: data.driver === DEFAULT_DRIVER ? null : data.driver,
}));

const driverItems = computed(() => [
    {
        label: trans('site_settings.mail.drivers.default'),
        value: DEFAULT_DRIVER,
    },
    ...props.drivers.map((driver) => ({
        label: trans(`site_settings.mail.drivers.${driver}`),
        value: driver,
    })),
]);

const endpointItems = computed(() =>
    props.mailgunEndpoints.map((endpoint) => ({
        label: trans(`site_settings.mail.mailgun_endpoints.${endpoint}`),
        value: endpoint,
    })),
);

/** Placeholder and help for a secret field, which is empty even when one is saved. */
function secretPlaceholder(secret: Secret): string | undefined {
    return props.savedSecrets[secret]
        ? trans('site_settings.mail.secret_placeholder')
        : undefined;
}

function secretHelp(secret: Secret): string | undefined {
    return props.savedSecrets[secret]
        ? trans('site_settings.mail.secret_saved')
        : undefined;
}

function onSubmit() {
    if (!props.canManage) return;

    form.submit(update(), {
        preserveScroll: true,
        onSuccess: () => {
            // Secrets are never sent back, so clear what was typed, and treat the rest as saved
            form.smtp_password = '';
            form.mailgun_secret = '';
            form.ses_secret = '';
            form.defaults();
        },
    });
}

const testForm = useForm({
    to: auth.value.user.email,
});

function sendTest() {
    testForm.submit(test(), { preserveScroll: true });
}
</script>

<template>
    <SiteSettingsSubLayout>
        <Head :title="trans('site_settings.mail.title')" />

        <form
            id="mail-settings"
            class="flex flex-col gap-4"
            @submit.prevent="onSubmit"
        >
            <UPageCard
                :title="trans('site_settings.mail.title')"
                :description="
                    canManage
                        ? trans('site_settings.mail.description')
                        : trans('site_settings.read_only')
                "
                orientation="horizontal"
                variant="naked"
            >
                <UButton
                    v-if="canManage"
                    class="w-fit lg:ms-auto"
                    color="primary"
                    form="mail-settings"
                    type="submit"
                    :loading="form.processing"
                    :label="trans('site_settings.save_changes')"
                />
            </UPageCard>

            <UPageCard variant="subtle">
                <UFormField
                    :label="trans('site_settings.mail.driver')"
                    :help="trans('site_settings.mail.driver_help')"
                    :error="form.errors.driver"
                >
                    <USelect
                        v-model="form.driver"
                        :items="driverItems"
                        :disabled="!canManage"
                        class="w-full sm:w-72"
                    />
                </UFormField>
            </UPageCard>

            <template v-if="form.driver !== DEFAULT_DRIVER">
                <UPageCard
                    variant="subtle"
                    :title="trans('site_settings.mail.sender')"
                >
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <UFormField
                            :label="trans('site_settings.mail.from_address')"
                            :error="form.errors.from_address"
                            required
                        >
                            <UInput
                                v-model="form.from_address"
                                type="email"
                                :disabled="!canManage"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            :label="trans('site_settings.mail.from_name')"
                            :error="form.errors.from_name"
                        >
                            <UInput
                                v-model="form.from_name"
                                :disabled="!canManage"
                                :placeholder="
                                    defaultFrom.name
                                        ? trans(
                                              'site_settings.mail.from_name_placeholder',
                                              { name: defaultFrom.name },
                                          )
                                        : undefined
                                "
                                class="w-full"
                            />
                        </UFormField>
                    </div>
                </UPageCard>

                <UPageCard
                    variant="subtle"
                    :title="trans('site_settings.mail.connection')"
                >
                    <div
                        v-if="form.driver === 'smtp'"
                        class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                    >
                        <UFormField
                            :label="trans('site_settings.mail.smtp_host')"
                            :error="form.errors.smtp_host"
                            required
                        >
                            <UInput
                                v-model="form.smtp_host"
                                :disabled="!canManage"
                                placeholder="smtp.example.com"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            :label="trans('site_settings.mail.smtp_port')"
                            :help="trans('site_settings.mail.smtp_port_help')"
                            :error="form.errors.smtp_port"
                            required
                        >
                            <UInputNumber
                                v-model="form.smtp_port"
                                :disabled="!canManage"
                                :min="1"
                                :max="65535"
                                :format-options="{ useGrouping: false }"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            :label="trans('site_settings.mail.smtp_username')"
                            :error="form.errors.smtp_username"
                        >
                            <UInput
                                v-model="form.smtp_username"
                                :disabled="!canManage"
                                autocomplete="off"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            :label="trans('site_settings.mail.smtp_password')"
                            :help="secretHelp('smtp_password')"
                            :error="form.errors.smtp_password"
                        >
                            <UInput
                                v-model="form.smtp_password"
                                type="password"
                                autocomplete="new-password"
                                :disabled="!canManage"
                                :placeholder="
                                    secretPlaceholder('smtp_password')
                                "
                                class="w-full"
                            />
                        </UFormField>
                    </div>

                    <div
                        v-else-if="form.driver === 'mailgun'"
                        class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                    >
                        <UFormField
                            :label="trans('site_settings.mail.mailgun_domain')"
                            :error="form.errors.mailgun_domain"
                            required
                        >
                            <UInput
                                v-model="form.mailgun_domain"
                                :disabled="!canManage"
                                placeholder="mg.example.com"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            :label="
                                trans('site_settings.mail.mailgun_endpoint')
                            "
                            :error="form.errors.mailgun_endpoint"
                            required
                        >
                            <USelect
                                v-model="form.mailgun_endpoint"
                                :items="endpointItems"
                                :disabled="!canManage"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            class="sm:col-span-2"
                            :label="trans('site_settings.mail.mailgun_secret')"
                            :help="secretHelp('mailgun_secret')"
                            :error="form.errors.mailgun_secret"
                            :required="!savedSecrets.mailgun_secret"
                        >
                            <UInput
                                v-model="form.mailgun_secret"
                                type="password"
                                autocomplete="new-password"
                                :disabled="!canManage"
                                :placeholder="
                                    secretPlaceholder('mailgun_secret')
                                "
                                class="w-full"
                            />
                        </UFormField>
                    </div>

                    <div
                        v-else-if="form.driver === 'ses'"
                        class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                    >
                        <UFormField
                            :label="trans('site_settings.mail.ses_key')"
                            :error="form.errors.ses_key"
                            required
                        >
                            <UInput
                                v-model="form.ses_key"
                                :disabled="!canManage"
                                autocomplete="off"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            :label="trans('site_settings.mail.ses_region')"
                            :error="form.errors.ses_region"
                            required
                        >
                            <UInput
                                v-model="form.ses_region"
                                :disabled="!canManage"
                                :placeholder="
                                    trans(
                                        'site_settings.mail.ses_region_placeholder',
                                    )
                                "
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            class="sm:col-span-2"
                            :label="trans('site_settings.mail.ses_secret')"
                            :help="secretHelp('ses_secret')"
                            :error="form.errors.ses_secret"
                            :required="!savedSecrets.ses_secret"
                        >
                            <UInput
                                v-model="form.ses_secret"
                                type="password"
                                autocomplete="new-password"
                                :disabled="!canManage"
                                :placeholder="secretPlaceholder('ses_secret')"
                                class="w-full"
                            />
                        </UFormField>
                    </div>
                </UPageCard>
            </template>
        </form>

        <UPageCard
            v-if="canManage"
            variant="subtle"
            :title="trans('site_settings.mail.test')"
            :description="
                form.isDirty
                    ? trans('site_settings.mail.test_save_first')
                    : trans('site_settings.mail.test_description')
            "
        >
            <form
                class="flex flex-col gap-2 sm:flex-row sm:items-start"
                @submit.prevent="sendTest"
            >
                <UFormField
                    class="flex-1"
                    :label="trans('site_settings.mail.test_to')"
                    :error="testForm.errors.to"
                >
                    <UInput v-model="testForm.to" type="email" class="w-full" />
                </UFormField>
                <UButton
                    type="submit"
                    class="w-fit sm:mt-6"
                    color="neutral"
                    variant="subtle"
                    icon="i-lucide-send"
                    :disabled="form.isDirty"
                    :loading="testForm.processing"
                    :label="trans('site_settings.mail.test_send')"
                />
            </form>
        </UPageCard>
    </SiteSettingsSubLayout>
</template>
