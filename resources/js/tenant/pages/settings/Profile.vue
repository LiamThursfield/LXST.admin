<script setup lang="ts">
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
    name: z.string().min(2, 'Too short'),
    email: z.email(),
});

type ProfileSchema = z.output<typeof profileSchema>;
const auth = useAuth();

const profile = reactive<Partial<ProfileSchema>>({
    name: auth.value.user.name,
    email: auth.value.user.email,
});

const form = useForm<Partial<ProfileSchema>>({
    name: '',
    email: '',
});

const toast = useToast();
async function onSubmit(event: FormSubmitEvent<ProfileSchema>) {
    form.name = event.data.name;
    form.email = event.data.email;
    form.submit(update());

    toast.add({
        title: 'Success',
        description: 'Your settings have been updated.',
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
                title="Profile"
                description="These informations will be displayed publicly."
                variant="naked"
                orientation="horizontal"
                class="mb-4"
            >
                <UButton
                    form="settings"
                    label="Save changes"
                    color="neutral"
                    type="submit"
                    class="w-fit lg:ms-auto"
                />
            </UPageCard>

            <UPageCard variant="subtle">
                <UFormField
                    name="name"
                    label="Name"
                    description="Will appear on receipts, invoices, and other communication."
                    required
                    class="flex items-start justify-between gap-4 max-sm:flex-col"
                >
                    <UInput v-model="profile.name" autocomplete="off" />
                </UFormField>
                <USeparator />
                <UFormField
                    name="email"
                    label="Email"
                    required
                    class="flex items-start justify-between gap-4 max-sm:flex-col"
                >
                    <template #description>
                        <p class="text-muted">
                            Used to sign in, for email receipts and product
                            updates.
                        </p>
                        <div
                            v-if="
                                mustVerifyEmail && !auth.user.email_verified_at
                            "
                        >
                            <p class="text-muted">
                                Your email address is unverified.
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
                                A new verification link has been sent to your
                                email address.
                            </div>
                        </div>
                    </template>
                    <UInput
                        v-model="profile.email"
                        type="email"
                        autocomplete="off"
                    />
                </UFormField>
            </UPageCard>
        </UForm>
    </SettingsSubLayout>
</template>

<style scoped></style>
