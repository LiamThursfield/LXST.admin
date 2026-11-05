<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/ServiceController';
import FileField from '@/tenant/components/file-manager/FileField.vue';
import type {
    ServiceData,
    ServiceFormOptions,
    ServiceOptionData,
} from '@/types';

/**
 * Creates, edits or shows a service. Only the details that apply to the chosen booking type are
 * shown; the others keep their values until saving, when the server clears them.
 */
const props = withDefaults(
    defineProps<
        ServiceFormOptions & {
            service?: ServiceData;
            readonly?: boolean;
            create?: boolean;
        }
    >(),
    {
        service: undefined,
        readonly: false,
        create: false,
    },
);

const { trans } = useTrans();

const service = props.service;

const form = useForm({
    name: service?.name ?? '',
    slug: service?.slug ?? '',
    description: service?.description ?? '',
    category_id: service?.category_id ?? null,
    image_file_id: service?.image_file_id ?? null,
    booking_type: service?.booking_type ?? props.bookingTypes[0].value,
    is_bookable_online: service?.is_bookable_online ?? true,
    price_type: service?.price_type ?? props.priceTypes[0].value,
    price: service?.price ?? '',
    duration_minutes: service?.duration_minutes ?? 60,
    buffer_before_minutes: service?.buffer_before_minutes ?? 0,
    buffer_after_minutes: service?.buffer_after_minutes ?? 0,
    capacity: service?.capacity ?? null,
    min_party_size: service?.min_party_size ?? 1,
    max_party_size: service?.max_party_size ?? null,
    hire_unit: service?.hire_unit ?? props.hireUnits[0].value,
    min_hire_units: service?.min_hire_units ?? 1,
    max_hire_units: service?.max_hire_units ?? null,
    travel_minutes: service?.travel_minutes ?? 0,
    required_resource_types: service?.required_resource_types ?? null,
    options: (service?.options ?? []).map((option) => ({
        ...option,
    })) as ServiceOptionData[],
    location_ids: [...(service?.location_ids ?? [])],
});

/** Server validation errors, including nested ones such as `options.0.name`. */
const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

const details = computed(
    () =>
        props.bookingTypes.find((type) => type.value === form.booking_type)
            ?.details ?? [],
);

const uses = (detail: string) => details.value.includes(detail);

/** What each booking needs: the service's own choice, or its booking type's default. */
const neededTypes = computed(
    () =>
        form.required_resource_types ??
        props.bookingTypes.find((type) => type.value === form.booking_type)
            ?.default_resource_types ??
        [],
);

function toggleNeededType(type: string, needed: boolean) {
    form.required_resource_types = needed
        ? [...neededTypes.value, type]
        : neededTypes.value.filter((existing) => existing !== type);
}

const hasAmount = computed(() => form.price_type !== 'on_quote');

const categoryItems = computed(() => [
    { value: null, label: trans('bookings.services.fields.no_category') },
    ...props.categories,
]);

const title = computed(() => {
    if (props.create) {
        return trans('bookings.services.create.title');
    }

    return trans(
        props.readonly
            ? 'bookings.services.show.title'
            : 'bookings.services.edit.title',
    );
});

const breadcrumbs = computed(() => [
    { label: trans('bookings.services.index.title'), to: index.url() },
    { label: service?.name ?? title.value },
]);

function addOption() {
    form.options.push({
        id: null,
        type: props.optionTypes[0].value as string,
        name: '',
        price_adjustment: '0.00',
        duration_adjustment: 0,
    });
}

function submit() {
    if (service) {
        form.submit(update(service.id));
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
        :readonly="readonly"
        :submit-label="
            create ? trans('bookings.services.create.submit') : undefined
        "
        @submit="submit"
    >
        <UPageCard
            variant="subtle"
            :title="trans('bookings.services.sections.details')"
        >
            <UFormField
                :label="trans('bookings.services.fields.name')"
                name="name"
                :error="form.errors.name"
                required
            >
                <UInput
                    v-model="form.name"
                    :disabled="readonly"
                    class="w-full"
                />
            </UFormField>

            <UFormField
                :label="trans('bookings.services.fields.description')"
                name="description"
                :error="form.errors.description"
            >
                <UTextarea
                    v-model="form.description"
                    :disabled="readonly"
                    :rows="3"
                    class="w-full"
                />
            </UFormField>
        </UPageCard>

        <UPageCard
            variant="subtle"
            :title="trans('bookings.services.sections.booking')"
        >
            <UFormField
                :label="trans('bookings.services.fields.booking_type')"
                name="booking_type"
                :error="form.errors.booking_type"
                required
            >
                <USelect
                    v-model="form.booking_type"
                    :items="bookingTypes"
                    :disabled="readonly"
                    class="w-full"
                />
            </UFormField>

            <UFormField
                v-if="uses('resources')"
                :label="
                    trans('bookings.services.fields.required_resource_types')
                "
                :help="
                    trans(
                        'bookings.services.fields.required_resource_types_help',
                    )
                "
                name="required_resource_types"
                :error="form.errors.required_resource_types"
            >
                <div class="flex flex-wrap gap-4">
                    <UCheckbox
                        v-for="type in resourceTypes"
                        :key="type.value"
                        :model-value="neededTypes.includes(String(type.value))"
                        :label="type.label"
                        :disabled="readonly"
                        @update:model-value="
                            (needed) =>
                                toggleNeededType(
                                    String(type.value),
                                    needed === true,
                                )
                        "
                    />
                </div>
            </UFormField>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    v-if="uses('duration')"
                    :label="trans('bookings.services.fields.duration_minutes')"
                    name="duration_minutes"
                    :error="form.errors.duration_minutes"
                    required
                >
                    <UInput
                        v-model.number="form.duration_minutes"
                        type="number"
                        min="1"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    v-if="uses('capacity')"
                    :label="trans('bookings.services.fields.capacity')"
                    name="capacity"
                    :error="form.errors.capacity"
                    required
                >
                    <UInput
                        v-model.number="form.capacity"
                        type="number"
                        min="1"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>

                <template v-if="uses('party_size')">
                    <UFormField
                        :label="
                            trans('bookings.services.fields.min_party_size')
                        "
                        name="min_party_size"
                        :error="form.errors.min_party_size"
                        required
                    >
                        <UInput
                            v-model.number="form.min_party_size"
                            type="number"
                            min="1"
                            :disabled="readonly"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        :label="
                            trans('bookings.services.fields.max_party_size')
                        "
                        name="max_party_size"
                        :error="form.errors.max_party_size"
                        required
                    >
                        <UInput
                            v-model.number="form.max_party_size"
                            type="number"
                            min="1"
                            :disabled="readonly"
                            class="w-full"
                        />
                    </UFormField>
                </template>

                <template v-if="uses('hire')">
                    <UFormField
                        class="md:col-span-2"
                        :label="trans('bookings.services.fields.hire_unit')"
                        name="hire_unit"
                        :error="form.errors.hire_unit"
                        required
                    >
                        <USelect
                            v-model="form.hire_unit"
                            :items="hireUnits"
                            :disabled="readonly"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        :label="
                            trans('bookings.services.fields.min_hire_units')
                        "
                        name="min_hire_units"
                        :error="form.errors.min_hire_units"
                        required
                    >
                        <UInput
                            v-model.number="form.min_hire_units"
                            type="number"
                            min="1"
                            :disabled="readonly"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        :label="
                            trans('bookings.services.fields.max_hire_units')
                        "
                        :help="
                            trans(
                                'bookings.services.fields.max_hire_units_help',
                            )
                        "
                        name="max_hire_units"
                        :error="form.errors.max_hire_units"
                    >
                        <UInput
                            v-model.number="form.max_hire_units"
                            type="number"
                            min="1"
                            :disabled="readonly"
                            class="w-full"
                        />
                    </UFormField>
                </template>

                <UFormField
                    v-if="uses('travel')"
                    :label="trans('bookings.services.fields.travel_minutes')"
                    :help="trans('bookings.services.fields.travel_help')"
                    name="travel_minutes"
                    :error="form.errors.travel_minutes"
                >
                    <UInput
                        v-model.number="form.travel_minutes"
                        type="number"
                        min="0"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>

                <template v-if="uses('buffers')">
                    <UFormField
                        :label="
                            trans(
                                'bookings.services.fields.buffer_before_minutes',
                            )
                        "
                        :help="trans('bookings.services.fields.buffers_help')"
                        name="buffer_before_minutes"
                        :error="form.errors.buffer_before_minutes"
                    >
                        <UInput
                            v-model.number="form.buffer_before_minutes"
                            type="number"
                            min="0"
                            :disabled="readonly"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        :label="
                            trans(
                                'bookings.services.fields.buffer_after_minutes',
                            )
                        "
                        name="buffer_after_minutes"
                        :error="form.errors.buffer_after_minutes"
                    >
                        <UInput
                            v-model.number="form.buffer_after_minutes"
                            type="number"
                            min="0"
                            :disabled="readonly"
                            class="w-full"
                        />
                    </UFormField>
                </template>
            </div>
        </UPageCard>

        <UPageCard
            variant="subtle"
            :title="trans('bookings.services.sections.price')"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    :label="trans('bookings.services.fields.price_type')"
                    name="price_type"
                    :error="form.errors.price_type"
                    required
                >
                    <USelect
                        v-model="form.price_type"
                        :items="priceTypes"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    v-if="hasAmount"
                    :label="
                        trans(
                            uses('hire')
                                ? 'bookings.services.fields.price_per_unit'
                                : 'bookings.services.fields.price',
                            { currency },
                        )
                    "
                    name="price"
                    :error="form.errors.price"
                    required
                >
                    <UInput
                        v-model="form.price"
                        type="number"
                        min="0"
                        step="0.01"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>
            </div>
        </UPageCard>

        <UPageCard
            variant="subtle"
            :title="trans('bookings.services.sections.options')"
            :description="
                trans('bookings.services.sections.options_description')
            "
        >
            <p v-if="!form.options.length" class="text-muted text-sm">
                {{ trans('bookings.services.options.empty') }}
            </p>

            <div
                v-for="(option, optionIndex) in form.options"
                :key="optionIndex"
                class="border-default grid grid-cols-1 gap-4 border-b pb-4 md:grid-cols-2"
            >
                <UFormField
                    :label="trans('bookings.services.options.type')"
                    :name="`options.${optionIndex}.type`"
                    :error="errors[`options.${optionIndex}.type`]"
                    required
                >
                    <USelect
                        v-model="option.type"
                        :items="optionTypes"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="trans('bookings.services.options.name')"
                    :name="`options.${optionIndex}.name`"
                    :error="errors[`options.${optionIndex}.name`]"
                    required
                >
                    <UInput
                        v-model="option.name"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="
                        trans('bookings.services.options.price_adjustment', {
                            currency,
                        })
                    "
                    :help="trans('bookings.services.options.adjustment_help')"
                    :name="`options.${optionIndex}.price_adjustment`"
                    :error="errors[`options.${optionIndex}.price_adjustment`]"
                >
                    <UInput
                        v-model="option.price_adjustment"
                        type="number"
                        step="0.01"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="
                        trans('bookings.services.options.duration_adjustment')
                    "
                    :name="`options.${optionIndex}.duration_adjustment`"
                    :error="
                        errors[`options.${optionIndex}.duration_adjustment`]
                    "
                >
                    <UInput
                        v-model.number="option.duration_adjustment"
                        type="number"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>

                <div v-if="!readonly" class="md:col-span-2">
                    <UButton
                        color="neutral"
                        variant="ghost"
                        icon="i-lucide-trash"
                        :label="trans('bookings.services.options.remove')"
                        @click="form.options.splice(optionIndex, 1)"
                    />
                </div>
            </div>

            <div v-if="!readonly">
                <UButton
                    color="neutral"
                    variant="subtle"
                    icon="i-lucide-plus"
                    :label="trans('bookings.services.options.add')"
                    @click="addOption"
                />
            </div>
        </UPageCard>

        <template #sidebar>
            <UPageCard
                variant="subtle"
                :title="trans('bookings.services.sections.availability')"
            >
                <USwitch
                    v-model="form.is_bookable_online"
                    :disabled="readonly"
                    :label="
                        trans('bookings.services.fields.is_bookable_online')
                    "
                    :description="
                        trans(
                            'bookings.services.fields.is_bookable_online_help',
                        )
                    "
                />

                <UFormField
                    v-if="hasLocations"
                    :label="trans('bookings.services.fields.locations')"
                    :help="trans('bookings.services.fields.locations_help')"
                    name="location_ids"
                    :error="form.errors.location_ids"
                >
                    <USelectMenu
                        v-model="form.location_ids"
                        :items="locations"
                        value-key="value"
                        multiple
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>

                <div v-if="service?.resource_names?.length">
                    <p class="text-sm font-medium">
                        {{ trans('bookings.services.fields.resources') }}
                    </p>
                    <p class="text-muted text-sm">
                        {{ service.resource_names.join(', ') }}
                    </p>
                </div>
            </UPageCard>

            <UPageCard variant="subtle">
                <UFormField
                    :label="trans('bookings.services.fields.category')"
                    name="category_id"
                    :error="form.errors.category_id"
                >
                    <USelect
                        v-model="form.category_id"
                        :items="categoryItems"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="trans('bookings.services.fields.image')"
                    name="image_file_id"
                    :error="form.errors.image_file_id"
                >
                    <FileField
                        v-model="form.image_file_id"
                        images-only
                        :disabled="readonly"
                    />
                </UFormField>

                <UFormField
                    :label="trans('bookings.services.fields.slug')"
                    :help="trans('bookings.services.fields.slug_help')"
                    name="slug"
                    :error="form.errors.slug"
                >
                    <UInput
                        v-model="form.slug"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>
            </UPageCard>
        </template>
    </EditorLayout>
</template>
