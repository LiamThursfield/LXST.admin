<script lang="ts" setup>
import { Form } from '@inertiajs/vue3';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';

const open = ref(false);
const passwordInput = useTemplateRef('passwordInput');
</script>

<template>
    <UModal
        v-model:open="open"
        description="Once your account is deleted, all of its resources and data will also be permanently deleted. Please enter your password to confirm you would like to permanently delete your account."
        title="Are you sure you want to delete your account?"
    >
        <UButton color="error" label="Delete account" />

        <template #body>
            <Form
                v-slot="{ errors, processing, reset, clearErrors }"
                :options="{
                    preserveScroll: true,
                }"
                class="space-y-6"
                reset-on-success
                v-bind="ProfileController.destroy.form()"
                @error="() => passwordInput?.inputRef?.focus()"
            >
                <UFormField :error="errors.password" name="password">
                    <UInput
                        ref="passwordInput"
                        class="w-full"
                        placeholder="Password"
                        type="password"
                    />
                </UFormField>
                <div class="flex justify-end gap-2">
                    <UButton
                        color="neutral"
                        label="Cancel"
                        variant="subtle"
                        @click="
                            () => {
                                clearErrors();
                                reset();
                                open = false;
                            }
                        "
                    />
                    <UButton
                        :disabled="processing"
                        color="error"
                        label="Delete Account"
                        loading-auto
                        type="submit"
                        variant="solid"
                    />
                </div>
            </Form>
        </template>
    </UModal>
</template>
