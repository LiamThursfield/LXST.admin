<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
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

function discard() {
    form.reset();
    form.clearErrors();
}

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
        <SettingsPage
            :title="trans('site_settings.general.title')"
            :description="trans('site_settings.general.description')"
            :readonly="!canManage"
            form-id="general-settings"
            :is-dirty="form.isDirty"
            :processing="form.processing"
            @discard="discard"
        >
            <UForm
                id="general-settings"
                :schema="schema"
                :state="form"
                :disabled="!canManage"
                @submit="onSubmit"
            >
                <SettingsRow
                    :label="trans('site_settings.general.site_name')"
                    :description="trans('site_settings.general.site_name_help')"
                    required
                >
                    <UFormField name="site_name" :error="form.errors.site_name">
                        <UInput
                            v-model="form.site_name"
                            :aria-label="
                                trans('site_settings.general.site_name')
                            "
                            class="w-full sm:w-80"
                        />
                    </UFormField>
                </SettingsRow>
            </UForm>
        </SettingsPage>
    </SiteSettingsSubLayout>
</template>
