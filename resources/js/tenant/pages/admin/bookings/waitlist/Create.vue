<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    index,
    store,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/WaitlistController';
import type { SelectItem } from '@/types';

const props = defineProps<{
    services: Array<
        SelectItem & {
            uses_sessions: boolean;
            sessions: SelectItem[];
        }
    >;
    hasLocations: boolean;
    locations: SelectItem[];
}>();

const { trans } = useTrans();

const form = useForm({
    service_id: (props.services[0]?.value ?? null) as number | null,
    session_id: null as number | null,
    wanted_on: '',
    location_id: null as number | null,
    quantity: 1,
    customer_name: '',
    customer_email: '',
    customer_phone: '',
    notes: '',
});

const service = computed(() =>
    props.services.find((option) => option.value === form.service_id),
);

watch(
    () => form.service_id,
    () => {
        form.session_id = null;
    },
);

const breadcrumbs = computed(() => [
    { label: trans('bookings.waitlist.index.title'), to: index.url() },
    { label: trans('bookings.waitlist.create.title') },
]);
</script>

<template>
    <EditorLayout
        :title="trans('bookings.waitlist.create.title')"
        :breadcrumbs="breadcrumbs"
        :is-dirty="form.isDirty"
        :processing="form.processing"
        :submit-label="trans('bookings.waitlist.create.submit')"
        narrow
        @submit="form.submit(store())"
    >
        <UPageCard
            variant="subtle"
            :description="trans('bookings.waitlist.create.help')"
        >
            <UFormField
                :label="trans('bookings.waitlist.fields.service')"
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
                v-if="service?.uses_sessions"
                :label="trans('bookings.waitlist.fields.session')"
                name="session_id"
                :error="form.errors.session_id"
                required
            >
                <USelect
                    v-model="form.session_id"
                    :items="service.sessions"
                    class="w-full"
                />
            </UFormField>

            <UFormField
                v-else
                :label="trans('bookings.waitlist.fields.wanted_on')"
                name="wanted_on"
                :error="form.errors.wanted_on"
                required
            >
                <UInput v-model="form.wanted_on" type="date" class="w-full" />
            </UFormField>

            <UFormField
                v-if="hasLocations && !service?.uses_sessions"
                :label="trans('bookings.waitlist.fields.location')"
                name="location_id"
                :error="form.errors.location_id"
            >
                <USelect
                    v-model="form.location_id"
                    :items="locations"
                    class="w-full"
                />
            </UFormField>

            <UFormField
                :label="trans('bookings.waitlist.fields.quantity')"
                name="quantity"
                :error="form.errors.quantity"
            >
                <UInput
                    v-model.number="form.quantity"
                    type="number"
                    min="1"
                    class="w-full"
                />
            </UFormField>
        </UPageCard>

        <UPageCard variant="subtle">
            <UFormField
                :label="trans('bookings.waitlist.fields.customer_name')"
                name="customer_name"
                :error="form.errors.customer_name"
                required
            >
                <UInput v-model="form.customer_name" class="w-full" />
            </UFormField>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    :label="trans('bookings.waitlist.fields.customer_email')"
                    name="customer_email"
                    :error="form.errors.customer_email"
                >
                    <UInput
                        v-model="form.customer_email"
                        type="email"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="trans('bookings.waitlist.fields.customer_phone')"
                    name="customer_phone"
                    :error="form.errors.customer_phone"
                >
                    <UInput
                        v-model="form.customer_phone"
                        type="tel"
                        class="w-full"
                    />
                </UFormField>
            </div>

            <UFormField
                :label="trans('bookings.waitlist.fields.notes')"
                name="notes"
                :error="form.errors.notes"
            >
                <UTextarea v-model="form.notes" :rows="3" class="w-full" />
            </UFormField>
        </UPageCard>
    </EditorLayout>
</template>
