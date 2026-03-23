<script lang="ts" setup>
import { Head } from '@inertiajs/vue3';
import type { FormSubmitEvent } from '@nuxt/ui';
import * as z from 'zod';
import { update } from '@/routes/admin/settings/profile';
import { send } from '@/routes/verification';
import SettingsSubLayout from '@/sublayouts/admin/SettingsSubLayout.vue';

defineProps<{
    mustVerifyEmail: boolean;
    status?: string;
}>();

const profileSchema = z.object({
    first_name: z.string().min(1, 'Too short').max(50, 'Too long'),
    last_name: z.string().min(1, 'Too short').max(50, 'Too long'),
    email: z.email(),
});

type ProfileSchema = z.output<typeof profileSchema>;
const auth = useAuth();

const profile = reactive<Partial<ProfileSchema>>({
    first_name: auth.value.user.first_name,
    last_name: auth.value.user.last_name,
    email: auth.value.user.email,
});

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
    form.submit(update());

    toast.add({
        title: 'Success',
        description: 'Your profile has been updated.',
        icon: 'i-lucide-check',
        color: 'success',
    });
}
</script>

<template>
    <SettingsSubLayout>
        <Head title="Profile settings" />

        <UForm
            id="settings"
            :schema="profileSchema"
            :state="profile"
            @submit="onSubmit"
        >
            <UPageCard
                class="mb-4"
                description="This information will be displayed publicly."
                orientation="horizontal"
                title="Profile"
                variant="naked"
            >
                <UButton
                    class="w-fit lg:ms-auto"
                    color="primary"
                    form="settings"
                    label="Save changes"
                    type="submit"
                />
            </UPageCard>

            <UPageCard variant="subtle">
                <UFormField
                    class="flex items-start justify-between gap-4 max-sm:flex-col"
                    label="First Name"
                    name="first_name"
                    required
                >
                    <UInput v-model="profile.first_name" autocomplete="off" />
                </UFormField>

                <UFormField
                    class="flex items-start justify-between gap-4 max-sm:flex-col"
                    label="Last Name"
                    name="last_name"
                    required
                >
                    <UInput v-model="profile.last_name" autocomplete="off" />
                </UFormField>

                <USeparator />

                <UFormField
                    class="flex items-start justify-between gap-4 max-sm:flex-col"
                    label="Email"
                    name="email"
                    required
                >
                    <template #description>
                        <p class="text-muted">
                            Used to sign in, for email receipts and product
                            updates.
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
                        Your email address is unverified. <br />
                        <TextLink
                            :href="send()"
                            as="button"
                            class="text-primary text-sm font-medium"
                        >
                            Click here to resend the verification email.
                        </TextLink>
                    </p>

                    <div
                        v-if="status === 'verification-link-sent'"
                        class="mt-2 text-sm font-medium text-green-600"
                    >
                        A new verification link has been sent to your email
                        address.
                    </div>
                </div>
            </UPageCard>
        </UForm>
    </SettingsSubLayout>
</template>

<style scoped></style>
