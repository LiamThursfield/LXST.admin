<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
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
        <Head :title="trans('site_settings.integrations.title')" />

        <UForm
            id="integration-settings"
            :schema="schema"
            :state="form"
            :disabled="!canManage"
            @submit="onSubmit"
        >
            <UPageCard
                class="mb-4"
                :title="trans('site_settings.integrations.title')"
                :description="
                    canManage
                        ? trans('site_settings.integrations.description')
                        : trans('site_settings.read_only')
                "
                orientation="horizontal"
                variant="naked"
            >
                <UButton
                    v-if="canManage"
                    class="w-fit lg:ms-auto"
                    color="primary"
                    form="integration-settings"
                    type="submit"
                    :loading="form.processing"
                    :label="trans('site_settings.save_changes')"
                />
            </UPageCard>

            <UPageCard variant="subtle">
                <UFormField
                    class="flex items-start justify-between gap-4 max-sm:flex-col"
                    :label="trans('site_settings.integrations.gtm_id')"
                    :help="trans('site_settings.integrations.gtm_id_help')"
                    name="gtm_id"
                    :error="form.errors.gtm_id"
                >
                    <UInput
                        v-model="form.gtm_id"
                        class="w-64"
                        placeholder="GTM-ABC1234"
                    />
                </UFormField>
            </UPageCard>
        </UForm>
    </SiteSettingsSubLayout>
</template>
