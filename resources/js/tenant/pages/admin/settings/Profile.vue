<script lang="ts" setup>
import { Head } from '@inertiajs/vue3';
import type { FormSubmitEvent } from '@nuxt/ui';
import * as z from 'zod';
import { timezoneOptions, userTimezone } from '@/lib/timezone';
import { update } from '@/routes/admin/settings/profile';
import { send } from '@/routes/verification';
import SettingsSubLayout from '@/sublayouts/admin/SettingsSubLayout.vue';

const { trans } = useTrans();

defineProps<{
    mustVerifyEmail: boolean;
    status?: string;
}>();

const profileSchema = z.object({
    first_name: z
        .string()
        .min(1, trans('settings.profile.validation.too_short'))
        .max(50, trans('settings.profile.validation.too_long')),
    last_name: z
        .string()
        .min(1, trans('settings.profile.validation.too_short'))
        .max(50, trans('settings.profile.validation.too_long')),
    email: z.email(),
    timezone: z
        .string()
        .min(1, trans('settings.profile.validation.timezone_required')),
});

type ProfileSchema = z.output<typeof profileSchema>;
const auth = useAuth();

const profile = reactive<Partial<ProfileSchema>>({
    first_name: auth.value.user.first_name,
    last_name: auth.value.user.last_name,
    email: auth.value.user.email,
    timezone: userTimezone(auth.value.user),
});

const timezones = timezoneOptions(profile.timezone);

const form = useForm<Partial<ProfileSchema>>({
    first_name: '',
    last_name: '',
    email: '',
    timezone: '',
});

const toast = useToast();
async function onSubmit(event: FormSubmitEvent<ProfileSchema>) {
    form.first_name = event.data.first_name;
    form.last_name = event.data.last_name;
    form.email = event.data.email;
    form.timezone = event.data.timezone;
    form.submit(update());

    toast.add({
        title: trans('settings.success'),
        description: trans('settings.profile.updated'),
        icon: 'i-lucide-check',
        color: 'success',
    });
}
</script>

<template>
    <SettingsSubLayout>
        <Head :title="trans('settings.profile.head')" />

        <UForm
            id="settings"
            :schema="profileSchema"
            :state="profile"
            @submit="onSubmit"
        >
            <UPageCard
                class="mb-4"
                :description="trans('settings.profile.description')"
                orientation="horizontal"
                :title="trans('settings.profile.title')"
                variant="naked"
            >
                <UButton
                    class="w-fit lg:ms-auto"
                    color="primary"
                    form="settings"
                    :label="trans('settings.save_changes')"
                    type="submit"
                />
            </UPageCard>

            <UPageCard variant="subtle">
                <UFormField
                    class="flex items-start justify-between gap-4 max-sm:flex-col"
                    :label="trans('settings.profile.first_name')"
                    name="first_name"
                    required
                >
                    <UInput v-model="profile.first_name" autocomplete="off" />
                </UFormField>

                <UFormField
                    class="flex items-start justify-between gap-4 max-sm:flex-col"
                    :label="trans('settings.profile.last_name')"
                    name="last_name"
                    required
                >
                    <UInput v-model="profile.last_name" autocomplete="off" />
                </UFormField>

                <USeparator />

                <UFormField
                    class="flex items-start justify-between gap-4 max-sm:flex-col"
                    :label="trans('settings.profile.email')"
                    name="email"
                    required
                >
                    <template #description>
                        <p class="text-muted">
                            {{ trans('settings.profile.email_description') }}
                        </p>
                    </template>
                    <UInput
                        v-model="profile.email"
                        autocomplete="off"
                        type="email"
                    />
                </UFormField>
                <div
                    v-if="mustVerifyEmail && !auth.user.email_verified_at"
                    class="flex flex-col"
                >
                    <p class="text-muted">
                        {{ trans('settings.profile.unverified') }} <br />
                        <TextLink
                            :href="send()"
                            as="button"
                            class="text-primary text-sm font-medium"
                        >
                            {{ trans('settings.profile.resend_verification') }}
                        </TextLink>
                    </p>

                    <div
                        v-if="status === 'verification-link-sent'"
                        class="mt-2 text-sm font-medium text-green-600"
                    >
                        {{ trans('settings.profile.verification_sent') }}
                    </div>
                </div>

                <USeparator />

                <UFormField
                    class="flex items-start justify-between gap-4 max-sm:flex-col"
                    :label="trans('settings.profile.timezone')"
                    name="timezone"
                    required
                >
                    <template #description>
                        <p class="text-muted">
                            {{ trans('settings.profile.timezone_description') }}
                        </p>
                    </template>
                    <USelectMenu
                        v-model="profile.timezone"
                        :items="timezones"
                        class="w-64"
                    />
                </UFormField>
            </UPageCard>
        </UForm>
    </SettingsSubLayout>
</template>

<style scoped></style>
