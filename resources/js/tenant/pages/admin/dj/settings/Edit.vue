<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { update } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/DjSettingsController';
import { timezoneOptions } from '@/lib/timezone';
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
const toggles = [
    'allow_custom_requests',
    'show_public_list',
    'allow_upvotes',
    'show_pass_labels',
] as const;

function onSubmit() {
    form.submit(update(), { preserveScroll: true });
}
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('dj.settings.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('dj.settings.title')" />
            <form
                class="mx-auto flex w-full max-w-3xl flex-col gap-6"
                @submit.prevent="onSubmit"
            >
                <p class="text-muted text-sm">
                    {{ trans('dj.settings.description') }}
                </p>

                <fieldset :disabled="!canManage" class="flex flex-col gap-6">
                    <UPageCard
                        :title="trans('dj.settings.sections.gigs')"
                        variant="subtle"
                    >
                        <div class="grid grid-cols-1 gap-4">
                            <UFormField
                                :label="
                                    trans('dj.settings.fields.default_timezone')
                                "
                                :help="
                                    trans(
                                        'dj.settings.fields.default_timezone_help',
                                    )
                                "
                                :error="form.errors.default_timezone"
                                required
                            >
                                <USelectMenu
                                    v-model="form.default_timezone"
                                    :items="timezones"
                                    value-key="value"
                                    class="w-full"
                                />
                            </UFormField>
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
                                class="md:col-span-2"
                                :label="
                                    trans(
                                        'dj.settings.fields.hosted_page_enabled',
                                    )
                                "
                                :description="
                                    trans(
                                        'dj.settings.fields.hosted_page_enabled_help',
                                    )
                                "
                            />
                        </div>
                    </UPageCard>

                    <UPageCard
                        :title="trans('dj.settings.sections.requests')"
                        :description="
                            trans('dj.settings.sections.requests_help')
                        "
                        variant="subtle"
                    >
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
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
                            <UFormField
                                :label="
                                    trans(
                                        'dj.request_settings.fields.max_requests_per_visitor',
                                    )
                                "
                                :help="
                                    trans(
                                        'dj.request_settings.fields.max_requests_per_visitor_help',
                                    )
                                "
                                :error="form.errors.max_requests_per_visitor"
                            >
                                <UInput
                                    v-model="form.max_requests_per_visitor"
                                    type="number"
                                    min="0"
                                    class="w-full"
                                />
                            </UFormField>
                            <UFormField
                                :label="
                                    trans(
                                        'dj.request_settings.fields.max_pending_requests',
                                    )
                                "
                                :help="
                                    trans(
                                        'dj.request_settings.fields.max_pending_requests_help',
                                    )
                                "
                                :error="form.errors.max_pending_requests"
                            >
                                <UInput
                                    v-model="form.max_pending_requests"
                                    type="number"
                                    min="0"
                                    class="w-full"
                                />
                            </UFormField>
                            <UFormField
                                v-for="toggle in toggles"
                                :key="toggle"
                                :error="form.errors[toggle]"
                                class="md:col-span-2"
                            >
                                <USwitch
                                    v-model="form[toggle]"
                                    :label="
                                        trans(
                                            `dj.request_settings.fields.${toggle}`,
                                        )
                                    "
                                />
                            </UFormField>
                        </div>
                    </UPageCard>
                </fieldset>

                <div v-if="canManage" class="flex justify-end">
                    <UButton
                        type="submit"
                        :loading="form.processing"
                        :label="trans('dj.settings.save')"
                    />
                </div>
            </form>
        </template>
    </UDashboardPanel>
</template>
