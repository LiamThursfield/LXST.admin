<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/GigController';
import { timezoneOptions } from '@/lib/timezone';
import type { GigData, RequestDefaults, SelectItem } from '@/types';

const props = defineProps<{
    gig?: GigData;
    defaultTimezone?: string;
    defaults: RequestDefaults;
    requestsModes: SelectItem[];
}>();

const { trans } = useTrans();

/**
 * Request settings are sent as null to follow the default. Select items
 * can't have an empty value, so the "Default" option uses a sentinel.
 */
const DEFAULT = 'default';
const overrides = props.gig?.request_settings ?? {};

const toChoice = (value: boolean | null | undefined) =>
    value === true ? 'yes' : value === false ? 'no' : DEFAULT;
const toNumber = (value: number | null | undefined) =>
    value === null || value === undefined ? '' : String(value);

const form = useForm({
    name: props.gig?.name ?? '',
    venue: props.gig?.venue ?? '',
    location: props.gig?.location ?? '',
    timezone: props.gig?.timezone ?? props.defaultTimezone ?? 'UTC',
    starts_at: props.gig?.starts_at_local ?? '',
    ends_at: props.gig?.ends_at_local ?? '',
    notes: props.gig?.notes ?? '',
    request_settings: {
        requests_mode: overrides.requests_mode ?? DEFAULT,
        requests_open_minutes_before: toNumber(
            overrides.requests_open_minutes_before,
        ),
        max_requests_per_visitor: toNumber(overrides.max_requests_per_visitor),
        max_pending_requests: toNumber(overrides.max_pending_requests),
        allow_custom_requests: toChoice(overrides.allow_custom_requests),
        show_public_list: toChoice(overrides.show_public_list),
        allow_upvotes: toChoice(overrides.allow_upvotes),
    },
});

const timezones = computed(() =>
    timezoneOptions(form.timezone).map((zone) => ({
        value: zone,
        label: zone,
    })),
);

const yesNo = (value: boolean) =>
    value ? trans('dj.request_settings.yes') : trans('dj.request_settings.no');
const limit = (value: number) =>
    value === 0 ? trans('dj.request_settings.no_limit') : String(value);
const withDefault = (label: string) =>
    trans('dj.request_settings.default', { value: label });

const modeItems = computed(() => [
    { value: DEFAULT, label: withDefault(props.defaults.requests_mode_label) },
    ...props.requestsModes.map((mode) => ({
        value: String(mode.value),
        label: mode.label,
    })),
]);

const choiceItems = (defaultValue: boolean) => [
    { value: DEFAULT, label: withDefault(yesNo(defaultValue)) },
    { value: 'yes', label: trans('dj.request_settings.yes') },
    { value: 'no', label: trans('dj.request_settings.no') },
];

const toggles = [
    'allow_custom_requests',
    'show_public_list',
    'allow_upvotes',
] as const;

const scheduled = computed(
    () =>
        form.request_settings.requests_mode === 'scheduled' ||
        (form.request_settings.requests_mode === DEFAULT &&
            props.defaults.requests_mode === 'scheduled'),
);

function onSubmit() {
    const choice = (value: string) =>
        value === 'yes' ? true : value === 'no' ? false : null;
    const number = (value: string) => (value === '' ? null : Number(value));

    form.transform((data) => ({
        ...data,
        request_settings: {
            requests_mode:
                data.request_settings.requests_mode === DEFAULT
                    ? null
                    : data.request_settings.requests_mode,
            requests_open_minutes_before: number(
                data.request_settings.requests_open_minutes_before,
            ),
            max_requests_per_visitor: number(
                data.request_settings.max_requests_per_visitor,
            ),
            max_pending_requests: number(
                data.request_settings.max_pending_requests,
            ),
            allow_custom_requests: choice(
                data.request_settings.allow_custom_requests,
            ),
            show_public_list: choice(data.request_settings.show_public_list),
            allow_upvotes: choice(data.request_settings.allow_upvotes),
        },
    })).submit(props.gig ? update(props.gig.id) : store(), {
        preserveScroll: true,
    });
}
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="onSubmit">
        <UPageCard :title="trans('dj.gigs.sections.details')" variant="subtle">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    :label="trans('dj.gigs.fields.name')"
                    :error="form.errors.name"
                    class="md:col-span-2"
                    required
                >
                    <UInput v-model="form.name" class="w-full" />
                </UFormField>
                <UFormField
                    :label="trans('dj.gigs.fields.venue')"
                    :error="form.errors.venue"
                >
                    <UInput v-model="form.venue" class="w-full" />
                </UFormField>
                <UFormField
                    :label="trans('dj.gigs.fields.location')"
                    :help="trans('dj.gigs.fields.location_help')"
                    :error="form.errors.location"
                >
                    <UInput v-model="form.location" class="w-full" />
                </UFormField>
                <UFormField
                    :label="trans('dj.gigs.fields.timezone')"
                    :help="trans('dj.gigs.fields.timezone_help')"
                    :error="form.errors.timezone"
                    class="md:col-span-2"
                    required
                >
                    <USelectMenu
                        v-model="form.timezone"
                        :items="timezones"
                        value-key="value"
                        class="w-full"
                    />
                </UFormField>
                <UFormField
                    :label="trans('dj.gigs.fields.starts_at')"
                    :error="form.errors.starts_at"
                    required
                >
                    <UInput
                        v-model="form.starts_at"
                        type="datetime-local"
                        class="w-full"
                    />
                </UFormField>
                <UFormField
                    :label="trans('dj.gigs.fields.ends_at')"
                    :error="form.errors.ends_at"
                    required
                >
                    <UInput
                        v-model="form.ends_at"
                        type="datetime-local"
                        class="w-full"
                    />
                </UFormField>
                <UFormField
                    :label="trans('dj.gigs.fields.notes')"
                    :error="form.errors.notes"
                    class="md:col-span-2"
                >
                    <UTextarea v-model="form.notes" :rows="3" class="w-full" />
                </UFormField>
            </div>
        </UPageCard>

        <UPageCard
            :title="trans('dj.gigs.sections.requests')"
            :description="trans('dj.gigs.sections.requests_help')"
            variant="subtle"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    :label="trans('dj.request_settings.fields.requests_mode')"
                    :error="form.errors['request_settings.requests_mode']"
                >
                    <USelect
                        v-model="form.request_settings.requests_mode"
                        :items="modeItems"
                        class="w-full"
                    />
                </UFormField>
                <UFormField
                    v-if="scheduled"
                    :label="
                        trans(
                            'dj.request_settings.fields.requests_open_minutes_before',
                        )
                    "
                    :error="
                        form.errors[
                            'request_settings.requests_open_minutes_before'
                        ]
                    "
                >
                    <UInput
                        v-model="
                            form.request_settings.requests_open_minutes_before
                        "
                        type="number"
                        min="0"
                        class="w-full"
                        :placeholder="
                            withDefault(
                                String(defaults.requests_open_minutes_before),
                            )
                        "
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
                    :error="
                        form.errors['request_settings.max_requests_per_visitor']
                    "
                >
                    <UInput
                        v-model="form.request_settings.max_requests_per_visitor"
                        type="number"
                        min="0"
                        class="w-full"
                        :placeholder="
                            withDefault(
                                limit(defaults.max_requests_per_visitor),
                            )
                        "
                    />
                </UFormField>
                <UFormField
                    :label="
                        trans('dj.request_settings.fields.max_pending_requests')
                    "
                    :help="
                        trans(
                            'dj.request_settings.fields.max_pending_requests_help',
                        )
                    "
                    :error="
                        form.errors['request_settings.max_pending_requests']
                    "
                >
                    <UInput
                        v-model="form.request_settings.max_pending_requests"
                        type="number"
                        min="0"
                        class="w-full"
                        :placeholder="
                            withDefault(limit(defaults.max_pending_requests))
                        "
                    />
                </UFormField>
                <UFormField
                    v-for="toggle in toggles"
                    :key="toggle"
                    :label="trans(`dj.request_settings.fields.${toggle}`)"
                    :error="form.errors[`request_settings.${toggle}`]"
                >
                    <USelect
                        v-model="form.request_settings[toggle]"
                        :items="choiceItems(defaults[toggle])"
                        class="w-full"
                    />
                </UFormField>
            </div>
        </UPageCard>

        <div class="flex justify-end">
            <UButton
                type="submit"
                :loading="form.processing"
                :label="trans('dj.gigs.save')"
            />
        </div>
    </form>
</template>
