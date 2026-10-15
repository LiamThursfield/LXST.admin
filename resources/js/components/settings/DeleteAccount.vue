<script lang="ts" setup>
import { Form } from '@inertiajs/vue3';

const { trans } = useTrans();

defineProps<{
    controller: {
        destroy: {
            form: () => any;
        };
    };
}>();

const open = ref(false);
const passwordInput = useTemplateRef('passwordInput');
</script>

<template>
    <UModal
        v-model:open="open"
        :description="trans('settings.delete_account.description')"
        :title="trans('settings.delete_account.title')"
    >
        <UButton color="error" :label="trans('settings.delete_account.open')" />

        <template #body>
            <Form
                v-slot="{ errors, processing, reset, clearErrors }"
                :options="{
                    preserveScroll: true,
                }"
                class="space-y-6"
                reset-on-success
                v-bind="controller.destroy.form()"
                @error="() => passwordInput?.inputRef?.focus()"
            >
                <UFormField :error="errors.password" name="password">
                    <UInput
                        ref="passwordInput"
                        class="w-full"
                        :placeholder="trans('settings.delete_account.password')"
                        type="password"
                    />
                </UFormField>
                <div class="flex justify-end gap-2">
                    <UButton
                        color="neutral"
                        :label="trans('common.actions.cancel')"
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
                        :label="trans('settings.delete_account.submit')"
                        loading-auto
                        type="submit"
                        variant="solid"
                    />
                </div>
            </Form>
        </template>
    </UModal>
</template>
