<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { update } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/DjSettingsController';
import { timezoneOptions } from '@/lib/timezone';
import SiteSettingsSubLayout from '@/sublayouts/admin/SiteSettingsSubLayout.vue';
import type { SelectItem } from '@/types';

const props = defineProps<{
    settings: {
        default_timezone: string;
        request_page_url: string | null;
        hosted_page_enabled: boolean;
        requests_mode: string;
        requests_open_minutes_before: number;
        max_requests_per_visitor: number;
        max_pending_requests: number;
        allow_custom_requests: boolean;
        show_public_list: boolean;
        allow_upvotes: boolean;
        show_pass_labels: boolean;
        show_tracklist: boolean;
        tracklist_per_page: number;
        tracklist_delay_minutes: number;
    };
    requestsModes: SelectItem[];
    canManage: boolean;
}>();

const { trans } = useTrans();

const form = useForm({
    ...props.settings,
    request_page_url: props.settings.request_page_url ?? '',
});

const timezones = computed(() =>
    timezoneOptions(form.default_timezone).map((zone) => ({
        value: zone,
        label: zone,
    })),
);
const options = [
    'allow_custom_requests',
    'show_public_list',
    'allow_upvotes',
    'show_pass_labels',
] as const;
const limits = ['max_requests_per_visitor', 'max_pending_requests'] as const;

function discard() {
    form.reset();
    form.clearErrors();
}

function onSubmit() {
    if (!props.canManage) {
        return;
    }

    form.submit(update(), {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
}
</script>

<template>
    <SiteSettingsSubLayout>
        <SettingsPage
            :title="trans('dj.settings.title')"
            :description="trans('dj.settings.description')"
            :readonly="!canManage"
            form-id="dj-settings"
            :is-dirty="form.isDirty"
            :processing="form.processing"
            @discard="discard"
        >
            <form id="dj-settings" @submit.prevent="onSubmit">
                <fieldset :disabled="!canManage">
                    <SettingsRow
                        :label="trans('dj.settings.rows.timezone')"
                        :description="
                            trans('dj.settings.fields.default_timezone_help')
                        "
                        required
                    >
                        <UFormField :error="form.errors.default_timezone">
                            <USelectMenu
                                v-model="form.default_timezone"
                                :items="timezones"
                                value-key="value"
                                :aria-label="
                                    trans('dj.settings.fields.default_timezone')
                                "
                                class="w-full sm:w-80"
                            />
                        </UFormField>
                    </SettingsRow>

                    <SettingsRow
                        :label="trans('dj.settings.rows.request_page')"
                        :description="
                            trans('dj.settings.rows.request_page_help')
                        "
                    >
                        <UFormField
                            :label="
                                trans('dj.settings.fields.request_page_url')
                            "
                            :help="
                                trans(
                                    'dj.settings.fields.request_page_url_help',
                                )
                            "
                            :error="form.errors.request_page_url"
                        >
                            <UInput
                                v-model="form.request_page_url"
                                type="url"
                                placeholder="https://mydjsite.com/request?gig={gig}"
                                class="w-full font-mono"
                            />
                        </UFormField>
                        <USwitch
                            v-model="form.hosted_page_enabled"
                            :label="
                                trans('dj.settings.fields.hosted_page_enabled')
                            "
                            :description="
                                trans(
                                    'dj.settings.fields.hosted_page_enabled_help',
                                )
                            "
                        />
                    </SettingsRow>

                    <SettingsRow
                        :label="trans('dj.settings.rows.requests')"
                        :description="trans('dj.settings.rows.requests_help')"
                    >
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <UFormField
                                :label="
                                    trans(
                                        'dj.request_settings.fields.requests_mode',
                                    )
                                "
                                :error="form.errors.requests_mode"
                            >
                                <USelect
                                    v-model="form.requests_mode"
                                    :items="requestsModes"
                                    class="w-full"
                                />
                            </UFormField>
                            <UFormField
                                v-if="form.requests_mode === 'scheduled'"
                                :label="
                                    trans(
                                        'dj.request_settings.fields.requests_open_minutes_before',
                                    )
                                "
                                :error="
                                    form.errors.requests_open_minutes_before
                                "
                            >
                                <UInput
                                    v-model="form.requests_open_minutes_before"
                                    type="number"
                                    min="0"
                                    class="w-full"
                                />
                            </UFormField>
                        </div>
                    </SettingsRow>

                    <SettingsRow
                        :label="trans('dj.settings.rows.limits')"
                        :description="trans('dj.settings.rows.limits_help')"
                    >
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <UFormField
                                v-for="field in limits"
                                :key="field"
                                :label="
                                    trans(`dj.request_settings.fields.${field}`)
                                "
                                :help="
                                    trans(
                                        `dj.request_settings.fields.${field}_help`,
                                    )
                                "
                                :error="form.errors[field]"
                            >
                                <UInput
                                    v-model="form[field]"
                                    type="number"
                                    min="0"
                                    class="w-full"
                                />
                            </UFormField>
                        </div>
                    </SettingsRow>

                    <SettingsRow
                        :label="trans('dj.settings.rows.options')"
                        :description="trans('dj.settings.rows.options_help')"
                    >
                        <UFormField
                            v-for="option in options"
                            :key="option"
                            :error="form.errors[option]"
                        >
                            <USwitch
                                v-model="form[option]"
                                :label="
                                    trans(
                                        `dj.request_settings.fields.${option}`,
                                    )
                                "
                            />
                        </UFormField>
                    </SettingsRow>

                    <SettingsRow
                        :label="trans('dj.settings.rows.tracklist')"
                        :description="trans('dj.settings.rows.tracklist_help')"
                    >
                        <UFormField :error="form.errors.show_tracklist">
                            <USwitch
                                v-model="form.show_tracklist"
                                :label="
                                    trans(
                                        'dj.request_settings.fields.show_tracklist',
                                    )
                                "
                            />
                        </UFormField>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <UFormField
                                :label="
                                    trans(
                                        'dj.request_settings.fields.tracklist_per_page',
                                    )
                                "
                                :error="form.errors.tracklist_per_page"
                            >
                                <UInput
                                    v-model="form.tracklist_per_page"
                                    type="number"
                                    min="0"
                                    class="w-full"
                                />
                            </UFormField>
                            <UFormField
                                :label="
                                    trans(
                                        'dj.request_settings.fields.tracklist_delay_minutes',
                                    )
                                "
                                :help="
                                    trans(
                                        'dj.request_settings.fields.tracklist_delay_minutes_help',
                                    )
                                "
                                :error="form.errors.tracklist_delay_minutes"
                            >
                                <UInput
                                    v-model="form.tracklist_delay_minutes"
                                    type="number"
                                    min="0"
                                    class="w-full"
                                />
                            </UFormField>
                        </div>
                    </SettingsRow>
                </fieldset>
            </form>
        </SettingsPage>
    </SiteSettingsSubLayout>
</template>
