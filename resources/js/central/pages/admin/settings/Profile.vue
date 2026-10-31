<script lang="ts" setup>
import type { FormSubmitEvent } from '@nuxt/ui';
import * as z from 'zod';
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
});

type ProfileSchema = z.output<typeof profileSchema>;
const auth = useAuth();

/** The profile as saved, for Discard. */
const saved = () => ({
    first_name: auth.value.user.first_name,
    last_name: auth.value.user.last_name,
    email: auth.value.user.email,
});

const profile = reactive<Partial<ProfileSchema>>(saved());

const { isDirty, markClean } = useIsDirty(() => ({ ...profile }));

function discard() {
    Object.assign(profile, saved());
    form.clearErrors();
}

const form = useForm<Partial<ProfileSchema>>({
    first_name: '',
    last_name: '',
    email: '',
});

const toast = useToast();
async function onSubmit(event: FormSubmitEvent<ProfileSchema>) {
    form.first_name = event.data.first_name;
    form.last_name = event.data.last_name;
    form.email = event.data.email;
    form.submit(update(), {
        onSuccess: () => {
            markClean();
            toast.add({
                title: trans('settings.success'),
                description: trans('settings.profile.updated'),
                icon: 'i-lucide-check',
                color: 'success',
            });
        },
    });
}
</script>

<template>
    <SettingsSubLayout>
        <SettingsPage
            :title="trans('settings.profile.title')"
            :description="trans('settings.profile.description')"
            form-id="settings"
            :is-dirty="isDirty"
            :processing="form.processing"
            @discard="discard"
        >
            <UForm
                id="settings"
                :schema="profileSchema"
                :state="profile"
                @submit="onSubmit"
            >
                <SettingsRow
                    :label="trans('settings.profile.name')"
                    :description="trans('settings.profile.name_description')"
                    required
                >
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <UFormField
                            :label="trans('settings.profile.first_name')"
                            name="first_name"
                            :error="form.errors.first_name"
                        >
                            <UInput
                                v-model="profile.first_name"
                                autocomplete="given-name"
                                class="w-full"
                            />
                        </UFormField>
                        <UFormField
                            :label="trans('settings.profile.last_name')"
                            name="last_name"
                            :error="form.errors.last_name"
                        >
                            <UInput
                                v-model="profile.last_name"
                                autocomplete="family-name"
                                class="w-full"
                            />
                        </UFormField>
                    </div>
                </SettingsRow>

                <SettingsRow
                    :label="trans('settings.profile.email')"
                    :description="trans('settings.profile.email_description')"
                    required
                >
                    <UFormField name="email" :error="form.errors.email">
                        <UInput
                            v-model="profile.email"
                            autocomplete="email"
                            type="email"
                            :aria-label="trans('settings.profile.email')"
                            class="w-full sm:w-80"
                        />
                    </UFormField>
                    <div
                        v-if="mustVerifyEmail && !auth.user.email_verified_at"
                        class="text-sm"
                    >
                        <p class="text-muted">
                            {{ trans('settings.profile.unverified') }}
                            <TextLink
                                :href="send()"
                                as="button"
                                class="text-primary font-medium"
                            >
                                {{
                                    trans(
                                        'settings.profile.resend_verification',
                                    )
                                }}
                            </TextLink>
                        </p>
                        <p
                            v-if="status === 'verification-link-sent'"
                            class="text-success mt-2 font-medium"
                        >
                            {{ trans('settings.profile.verification_sent') }}
                        </p>
                    </div>
                </SettingsRow>
            </UForm>
        </SettingsPage>
    </SettingsSubLayout>
</template>
