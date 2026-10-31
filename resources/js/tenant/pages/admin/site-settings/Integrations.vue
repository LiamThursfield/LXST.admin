<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import * as z from 'zod';
import { update } from '@/actions/App/Http/Controllers/Tenant/Admin/SiteSettings/IntegrationSettingsController';
import SiteSettingsSubLayout from '@/sublayouts/admin/SiteSettingsSubLayout.vue';

const props = defineProps<{
    settings: { gtm_id: string | null };
    /** False for users who can see the settings but not change them. */
    canManage: boolean;
}>();

const { trans } = useTrans();

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
});

function discard() {
    form.reset();
    form.clearErrors();
}

function onSubmit() {
    if (!props.canManage) return;

    // Show what was saved, which the server may have tidied (e.g. trimmed or upper-cased)
    form.submit(update(), {
        onSuccess: () => {
            form.gtm_id = props.settings.gtm_id ?? '';
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
            </UForm>
        </SettingsPage>
    </SiteSettingsSubLayout>
</template>
