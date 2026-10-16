<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import * as z from 'zod';
import { update } from '@/actions/App/Http/Controllers/Tenant/Admin/SiteSettings/GeneralSettingsController';
import SiteSettingsSubLayout from '@/sublayouts/admin/SiteSettingsSubLayout.vue';

const props = defineProps<{
    settings: { site_name: string };
    /** False for users who can see the settings but not change them. */
    canManage: boolean;
}>();

const { trans } = useTrans();

const schema = z.object({
    site_name: z
        .string()
        .trim()
        .min(1, trans('site_settings.general.validation.site_name_required'))
        .max(255),
});

const form = useForm({
    site_name: props.settings.site_name,
});

function onSubmit() {
    if (!props.canManage) return;

    // Show what was saved, which the server may have tidied (e.g. trimmed or upper-cased)
    form.submit(update(), {
        onSuccess: () => {
            form.site_name = props.settings.site_name;
            form.defaults();
        },
    });
}
</script>

<template>
    <SiteSettingsSubLayout>
        <Head :title="trans('site_settings.general.title')" />

        <UForm
            id="general-settings"
            :schema="schema"
            :state="form"
            :disabled="!canManage"
            @submit="onSubmit"
        >
            <UPageCard
                class="mb-4"
                :title="trans('site_settings.general.title')"
                :description="
                    canManage
                        ? trans('site_settings.general.description')
                        : trans('site_settings.read_only')
                "
                orientation="horizontal"
                variant="naked"
            >
                <UButton
                    v-if="canManage"
                    class="w-fit lg:ms-auto"
                    color="primary"
                    form="general-settings"
                    type="submit"
                    :loading="form.processing"
                    :label="trans('site_settings.save_changes')"
                />
            </UPageCard>

            <UPageCard variant="subtle">
                <UFormField
                    class="flex items-start justify-between gap-4 max-sm:flex-col"
                    :label="trans('site_settings.general.site_name')"
                    :help="trans('site_settings.general.site_name_help')"
                    name="site_name"
                    :error="form.errors.site_name"
                    required
                >
                    <UInput v-model="form.site_name" class="w-64" />
                </UFormField>
            </UPageCard>
        </UForm>
    </SiteSettingsSubLayout>
</template>
