<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/ServiceSessionController';
import type { ServiceSessionData, ServiceSessionFormOptions } from '@/types';

const props = defineProps<
    ServiceSessionFormOptions & {
        session?: ServiceSessionData;
    }
>();

const { trans } = useTrans();

const session = props.session;

const form = useForm({
    service_id:
        session?.service_id ??
        (props.services[0]?.value as number | undefined) ??
        null,
    location_id: session?.location_id ?? null,
    date: session?.date ?? '',
    time: session?.time ?? '',
    duration_minutes:
        session?.duration_minutes ?? props.services[0]?.duration_minutes ?? 60,
    capacity: session?.capacity ?? null,
    resource_ids: [...(session?.resource_ids ?? [])],
    repeat_times: 1,
});

const locationItems = computed(() => [
    { value: null, label: trans('bookings.sessions.fields.no_location') },
    ...props.locations,
]);

/** A new entry starts as long as its service, until changed. */
watch(
    () => form.service_id,
    (id) => {
        const length = props.services.find(
            (service) => service.value === id,
        )?.duration_minutes;

        if (length && !session) {
            form.duration_minutes = length;
        }
    },
);

const title = computed(() =>
    trans(
        session
            ? 'bookings.sessions.edit.title'
            : 'bookings.sessions.create.title',
    ),
);

const breadcrumbs = computed(() => [
    { label: trans('bookings.sessions.index.title'), to: index.url() },
    { label: session?.when ?? title.value },
]);

function submit() {
    if (session) {
        form.submit(update(session.id));
    } else {
        form.submit(store());
    }
}
</script>

<template>
    <EditorLayout
        :title="title"
        :breadcrumbs="breadcrumbs"
        :is-dirty="form.isDirty"
        :processing="form.processing"
        :submit-label="
            session ? undefined : trans('bookings.sessions.create.submit')
        "
        narrow
        @submit="submit"
    >
        <UAlert
            v-if="!services.length"
            color="warning"
            variant="subtle"
            :title="trans('bookings.sessions.no_services')"
        />

        <UPageCard
            variant="subtle"
            :description="trans('bookings.sessions.help')"
        >
            <UFormField
                :label="trans('bookings.sessions.fields.service')"
                name="service_id"
                :error="form.errors.service_id"
                required
            >
                <USelect
                    v-model="form.service_id"
                    :items="services"
                    class="w-full"
                />
            </UFormField>

            <UFormField
                v-if="hasLocations"
                :label="trans('bookings.sessions.fields.location')"
                name="location_id"
                :error="form.errors.location_id"
            >
                <USelect
                    v-model="form.location_id"
                    :items="locationItems"
                    class="w-full"
                />
            </UFormField>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <UFormField
                    :label="trans('bookings.sessions.fields.date')"
                    name="date"
                    :error="form.errors.date"
                    required
                >
                    <UInput v-model="form.date" type="date" class="w-full" />
                </UFormField>

                <UFormField
                    :label="trans('bookings.sessions.fields.time')"
                    name="time"
                    :error="form.errors.time"
                    required
                >
                    <UInput v-model="form.time" type="time" class="w-full" />
                </UFormField>

                <UFormField
                    :label="trans('bookings.sessions.fields.duration_minutes')"
                    name="duration_minutes"
                    :error="form.errors.duration_minutes"
                    required
                >
                    <UInput
                        v-model.number="form.duration_minutes"
                        type="number"
                        min="1"
                        class="w-full"
                    />
                </UFormField>
            </div>

            <UFormField
                :label="trans('bookings.sessions.fields.capacity')"
                :help="trans('bookings.sessions.fields.capacity_help')"
                name="capacity"
                :error="form.errors.capacity"
            >
                <UInput
                    v-model.number="form.capacity"
                    type="number"
                    min="1"
                    class="w-full"
                />
            </UFormField>

            <UFormField
                v-if="!session"
                :label="trans('bookings.sessions.fields.repeat_times')"
                :help="trans('bookings.sessions.fields.repeat_times_help')"
                name="repeat_times"
                :error="form.errors.repeat_times"
            >
                <UInput
                    v-model.number="form.repeat_times"
                    type="number"
                    min="1"
                    max="52"
                    class="w-full"
                />
            </UFormField>

            <UFormField
                :label="trans('bookings.sessions.fields.resources')"
                :help="trans('bookings.sessions.fields.resources_help')"
                name="resource_ids"
                :error="form.errors.resource_ids"
            >
                <USelectMenu
                    v-model="form.resource_ids"
                    :items="resources"
                    value-key="value"
                    multiple
                    class="w-full"
                />
            </UFormField>
        </UPageCard>
    </EditorLayout>
</template>
