<script setup lang="ts">
import { useForm, useHttp } from '@inertiajs/vue3';
import BookingAvailabilityController from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/BookingAvailabilityController';
import {
    index,
    store,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/BookingController';
import type {
    BookableServiceOption,
    BookingPrefill,
    SelectItem,
} from '@/types';

type Availability = {
    slots: Array<{ time: string; resources: string[] }>;
    sessions: Array<{ id: number; when: string; places_left: number }>;
};

const props = defineProps<{
    services: BookableServiceOption[];
    hasLocations: boolean;
    locations: SelectItem[];
    prefill: BookingPrefill | null;
    channels: SelectItem[];
}>();

const { trans, transChoice } = useTrans();

const prefill = props.prefill;

const form = useForm({
    waitlist_entry_id: prefill?.waitlist_entry_id ?? null,
    service_id: (prefill?.service_id ?? props.services[0]?.value ?? null) as
        number | null,
    location_id: (prefill?.location_id ?? null) as number | null,
    walk_in: false,
    ignore_rules: false,
    date: prefill?.date ?? '',
    time: null as string | null,
    session_id: null as number | null,
    quantity:
        prefill?.quantity ??
        props.services.find((option) => option.value === prefill?.service_id)
            ?.min_quantity ??
        props.services[0]?.min_quantity ??
        1,
    option_ids: [] as number[],
    resource_ids: [] as number[],
    repeat_weeks: null as number | null,
    repeat_times: 4,
    customer_name: prefill?.customer_name ?? '',
    customer_email: prefill?.customer_email ?? '',
    customer_phone: prefill?.customer_phone ?? '',
    channel: null as string | null,
    notes: prefill?.notes ?? '',
});

const service = computed(() =>
    props.services.find((option) => option.value === form.service_id),
);

const isEnquiry = computed(() => service.value?.booking_type === 'enquiry');

const usesSessions = computed(() =>
    ['class', 'ticket'].includes(service.value?.booking_type ?? ''),
);

const quantityLabel = computed(() => {
    if (service.value?.hire_unit === 'hour') {
        return trans('bookings.bookings.fields.quantity_hours');
    }

    if (service.value?.hire_unit === 'day') {
        return trans('bookings.bookings.fields.quantity_days');
    }

    if (service.value?.booking_type === 'table') {
        return trans('bookings.bookings.fields.quantity_party');
    }

    return usesSessions.value
        ? trans('bookings.bookings.fields.quantity_places')
        : null;
});

/** The locations this service is offered at. None chosen on the service means all of them. */
const locationItems = computed(() =>
    props.locations.filter(
        (location) =>
            !service.value?.location_ids.length ||
            service.value.location_ids.includes(Number(location.value)),
    ),
);

const http = useHttp();
const availability = ref<Availability>({ slots: [], sessions: [] });
const loaded = ref(false);

const slotItems = computed(() =>
    availability.value.slots.map((slot) => ({
        value: slot.time,
        label: slot.resources.length
            ? `${slot.time} · ${slot.resources.join(', ')}`
            : slot.time,
    })),
);

const sessionItems = computed(() =>
    availability.value.sessions.map((session) => ({
        value: session.id,
        label: trans('bookings.bookings.availability.session_label', {
            when: session.when,
            places: session.places_left,
        }),
    })),
);

/** A resource chosen per type, or nothing for anyone available. */
const chosenResources = ref<Record<string, number | null>>({});

watch(
    chosenResources,
    (chosen) => {
        form.resource_ids = Object.values(chosen).filter(
            (id): id is number => id !== null,
        );
    },
    { deep: true },
);

function resourceItems(resources: SelectItem[]): SelectItem[] {
    return [
        {
            value: null as unknown as number,
            label: trans('bookings.bookings.fields.anyone'),
        },
        ...resources,
    ] as SelectItem[];
}

async function loadAvailability() {
    form.time = null;
    form.session_id = null;
    loaded.value = false;
    availability.value = { slots: [], sessions: [] };

    if (
        !service.value ||
        isEnquiry.value ||
        form.walk_in ||
        (!usesSessions.value && !form.date)
    ) {
        return;
    }

    availability.value = (await http.get(
        BookingAvailabilityController.url({
            query: {
                service_id: service.value.value,
                location_id: form.location_id ?? undefined,
                date: form.date || undefined,
                quantity: form.quantity,
                option_ids: form.option_ids,
                resource_ids: form.resource_ids,
                ignore_rules: form.ignore_rules ? 1 : 0,
            },
        }),
    )) as Availability;
    loaded.value = true;
}

const reload = useDebounceFn(loadAvailability, 250);

watch(
    () => [
        form.location_id,
        form.date,
        form.quantity,
        form.option_ids,
        form.resource_ids,
        form.walk_in,
        form.ignore_rules,
    ],
    () => reload(),
    { deep: true },
);

watch(
    () => form.service_id,
    () => {
        form.option_ids = [];
        form.location_id = null;
        chosenResources.value = {};
        form.quantity = service.value?.min_quantity ?? 1;
        reload();
    },
);

onMounted(() => {
    if (usesSessions.value || form.date) {
        loadAvailability();
    }
});

const channelItems = computed(() => [
    {
        value: null as unknown as string,
        label: trans('bookings.bookings.channel.preference'),
    },
    ...props.channels,
]);

const repeatItems = computed(() => [
    {
        value: null as unknown as number,
        label: trans('bookings.bookings.repeat.none'),
    },
    ...[1, 2, 3, 4].map((weeks) => ({
        value: weeks,
        label: transChoice('bookings.bookings.repeat.every', weeks, {
            count: weeks,
        }),
    })),
]);

function toggleOption(id: number, type: string, chosen: boolean) {
    const others = form.option_ids.filter((existing) => existing !== id);

    // Only one variation can be chosen, so choosing one replaces another
    const kept =
        chosen && type === 'variation'
            ? others.filter(
                  (existing) =>
                      service.value?.options.find(
                          (option) => option.value === existing,
                      )?.type !== 'variation',
              )
            : others;

    form.option_ids = chosen ? [...kept, id] : kept;
}

const breadcrumbs = computed(() => [
    { label: trans('bookings.bookings.index.title'), to: index.url() },
    { label: trans('bookings.bookings.create.title') },
]);
</script>

<template>
    <EditorLayout
        :title="trans('bookings.bookings.create.title')"
        :breadcrumbs="breadcrumbs"
        :is-dirty="form.isDirty"
        :processing="form.processing"
        :submit-label="trans('bookings.bookings.create.submit')"
        narrow
        @submit="form.submit(store())"
    >
        <UPageCard
            variant="subtle"
            :title="trans('bookings.bookings.sections.what')"
        >
            <UFormField
                :label="trans('bookings.bookings.fields.service')"
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
                :label="trans('bookings.bookings.fields.location')"
                name="location_id"
                :error="form.errors.location_id"
            >
                <USelect
                    v-model="form.location_id"
                    :items="locationItems"
                    :placeholder="
                        trans('bookings.bookings.fields.any_location')
                    "
                    class="w-full"
                />
            </UFormField>

            <UFormField
                v-if="quantityLabel"
                :label="quantityLabel"
                name="quantity"
                :error="form.errors.quantity"
                required
            >
                <UInput
                    v-model.number="form.quantity"
                    type="number"
                    :min="service?.min_quantity ?? 1"
                    :max="service?.max_quantity ?? undefined"
                    class="w-full"
                />
            </UFormField>

            <UFormField
                v-if="service?.options.length"
                :label="trans('bookings.bookings.fields.options')"
                :error="form.errors.option_ids"
            >
                <div class="flex flex-col gap-2">
                    <UCheckbox
                        v-for="option in service.options"
                        :key="option.value"
                        :model-value="form.option_ids.includes(option.value)"
                        :label="option.label"
                        @update:model-value="
                            (chosen) =>
                                toggleOption(
                                    option.value,
                                    option.type,
                                    chosen === true,
                                )
                        "
                    />
                </div>
            </UFormField>

            <UFormField
                v-for="type in service?.resource_types ?? []"
                :key="type.value"
                :label="type.label"
            >
                <USelect
                    :model-value="chosenResources[type.value] ?? null"
                    :items="resourceItems(type.resources)"
                    class="w-full"
                    @update:model-value="
                        (id) => (chosenResources[type.value] = id)
                    "
                />
            </UFormField>
        </UPageCard>

        <UPageCard
            variant="subtle"
            :title="trans('bookings.bookings.sections.when')"
        >
            <p v-if="isEnquiry" class="text-muted text-sm">
                {{ trans('bookings.bookings.availability.enquiry_help') }}
            </p>

            <UFormField
                v-else-if="usesSessions"
                :label="trans('bookings.bookings.fields.session')"
                name="session_id"
                :error="form.errors.session_id"
                :help="
                    loaded && !sessionItems.length
                        ? trans('bookings.bookings.availability.no_sessions')
                        : undefined
                "
                required
            >
                <USelect
                    v-model="form.session_id"
                    :items="sessionItems"
                    :disabled="!sessionItems.length"
                    class="w-full"
                />
            </UFormField>

            <template v-else>
                <USwitch
                    v-model="form.walk_in"
                    :label="trans('bookings.bookings.walk_in.label')"
                    :description="trans('bookings.bookings.walk_in.help')"
                />

                <div
                    v-if="!form.walk_in"
                    class="grid grid-cols-1 gap-4 md:grid-cols-2"
                >
                    <UFormField
                        :label="trans('bookings.bookings.fields.date')"
                        name="date"
                        :error="form.errors.date"
                        required
                    >
                        <UInput
                            v-model="form.date"
                            type="date"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        :label="trans('bookings.bookings.fields.time')"
                        name="time"
                        :error="form.errors.time"
                        :help="
                            !form.date
                                ? trans(
                                      'bookings.bookings.availability.choose_first',
                                  )
                                : loaded && !slotItems.length
                                  ? trans('bookings.bookings.availability.none')
                                  : undefined
                        "
                        required
                    >
                        <USelect
                            v-model="form.time"
                            :items="slotItems"
                            :disabled="!slotItems.length"
                            :loading="http.processing"
                            class="w-full"
                        />
                    </UFormField>
                </div>
            </template>
            <div
                v-if="!isEnquiry && !usesSessions && !form.walk_in"
                class="grid grid-cols-1 gap-4 md:grid-cols-2"
            >
                <UFormField
                    :label="trans('bookings.bookings.repeat.label')"
                    :help="
                        form.repeat_weeks
                            ? trans('bookings.bookings.repeat.help')
                            : undefined
                    "
                    name="repeat_weeks"
                    :error="form.errors.repeat_weeks"
                >
                    <USelect
                        v-model="form.repeat_weeks"
                        :items="repeatItems"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    v-if="form.repeat_weeks"
                    :label="trans('bookings.bookings.repeat.times')"
                    name="repeat_times"
                    :error="form.errors.repeat_times"
                    required
                >
                    <UInput
                        v-model.number="form.repeat_times"
                        type="number"
                        min="2"
                        max="52"
                        class="w-full"
                    />
                </UFormField>
            </div>

            <USwitch
                v-if="!isEnquiry"
                v-model="form.ignore_rules"
                :label="trans('bookings.bookings.ignore_rules.label')"
                :description="trans('bookings.bookings.ignore_rules.help')"
            />
        </UPageCard>

        <UPageCard
            variant="subtle"
            :title="trans('bookings.bookings.sections.customer')"
        >
            <UFormField
                :label="trans('bookings.bookings.fields.customer_name')"
                name="customer_name"
                :error="form.errors.customer_name"
                required
            >
                <UInput v-model="form.customer_name" class="w-full" />
            </UFormField>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    :label="trans('bookings.bookings.fields.customer_email')"
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
                    :label="trans('bookings.bookings.fields.customer_phone')"
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
                :label="trans('bookings.bookings.channel.label')"
                :help="trans('bookings.bookings.channel.help')"
                name="channel"
                :error="form.errors.channel"
            >
                <USelect
                    v-model="form.channel"
                    :items="channelItems"
                    class="w-full"
                />
            </UFormField>

            <UFormField
                :label="trans('bookings.bookings.fields.notes')"
                name="notes"
                :error="form.errors.notes"
            >
                <UTextarea v-model="form.notes" :rows="3" class="w-full" />
            </UFormField>
        </UPageCard>
    </EditorLayout>
</template>
