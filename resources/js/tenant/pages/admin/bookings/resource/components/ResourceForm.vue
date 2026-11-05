<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import ResourceController from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/ResourceController';
import StaffController from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/StaffController';
import FileField from '@/tenant/components/file-manager/FileField.vue';
import type {
    BookableResourceData,
    BookableResourceFormOptions,
    ResourceServiceData,
} from '@/types';
import WorkingHoursFields from './WorkingHoursFields.vue';

/**
 * Creates, edits or shows a person, space, piece of equipment or vehicle, its usual working hours,
 * and the services it can do. People and everything else have their own lists, so a person's form
 * doesn't offer a type.
 */
const props = withDefaults(
    defineProps<
        BookableResourceFormOptions & {
            bookableResource?: BookableResourceData;
            readonly?: boolean;
            create?: boolean;
        }
    >(),
    {
        bookableResource: undefined,
        readonly: false,
        create: false,
    },
);

const { trans } = useTrans();

const resource = props.bookableResource;

const controller = props.forStaff ? StaffController : ResourceController;
const copy = props.forStaff ? 'bookings.staff' : 'bookings.resources';

const form = useForm({
    type:
        resource?.type ??
        (props.forStaff ? 'person' : (props.types[0].value as string)),
    name: resource?.name ?? '',
    slug: resource?.slug ?? '',
    description: resource?.description ?? '',
    image_file_id: resource?.image_file_id ?? null,
    location_id: resource?.location_id ?? null,
    user_id: resource?.user_id ?? null,
    job_title: resource?.job_title ?? '',
    capacity: resource?.capacity ?? null,
    combine_group: resource?.combine_group ?? '',
    is_active: resource?.is_active ?? true,
    is_public: resource?.is_public ?? false,
    working_hours: (resource?.working_hours ?? []).map((hour) => ({ ...hour })),
    services: (resource?.services ?? []).map((service) => ({
        ...service,
    })) as ResourceServiceData[],
});

/** Server validation errors, including nested ones such as `working_hours.0.starts_at`. */
const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

const hasCapacity = computed(() => form.type === 'space');

const userItems = computed(() => [
    { value: null, label: trans('bookings.resources.fields.no_user') },
    ...props.users,
]);

const locationItems = computed(() => [
    { value: null, label: trans('bookings.resources.fields.no_location') },
    ...props.locations,
]);

const title = computed(() => {
    if (props.create) {
        return trans(`${copy}.create.title`);
    }

    return trans(props.readonly ? `${copy}.show.title` : `${copy}.edit.title`);
});

const breadcrumbs = computed(() => [
    { label: trans(`${copy}.index.title`), to: controller.index.url() },
    { label: resource?.name ?? title.value },
]);

function chosenIndex(serviceId: string | number): number {
    return form.services.findIndex((service) => service.id === serviceId);
}

function toggleService(serviceId: string | number, chosen: boolean) {
    const at = chosenIndex(serviceId);

    if (chosen && at === -1) {
        form.services.push({
            id: Number(serviceId),
            price: null,
            duration_minutes: null,
        });
    } else if (!chosen && at !== -1) {
        form.services.splice(at, 1);
    }
}

function submit() {
    if (resource) {
        form.submit(controller.update(resource.id));
    } else {
        form.submit(controller.store());
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
        :submit-label="create ? trans(`${copy}.create.submit`) : undefined"
        @submit="submit"
    >
        <UPageCard
            variant="subtle"
            :title="trans('bookings.resources.sections.details')"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    v-if="!forStaff"
                    :label="trans('bookings.resources.fields.type')"
                    name="type"
                    :error="form.errors.type"
                    required
                >
                    <USelect
                        v-model="form.type"
                        :items="types"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="trans('bookings.resources.fields.name')"
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

                <template v-if="forStaff">
                    <UFormField
                        :label="trans('bookings.resources.fields.job_title')"
                        name="job_title"
                        :error="form.errors.job_title"
                    >
                        <UInput
                            v-model="form.job_title"
                            :disabled="readonly"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        :label="trans('bookings.resources.fields.user')"
                        :help="trans('bookings.resources.fields.user_help')"
                        name="user_id"
                        :error="form.errors.user_id"
                    >
                        <USelect
                            v-model="form.user_id"
                            :items="userItems"
                            :disabled="readonly"
                            class="w-full"
                        />
                    </UFormField>
                </template>

                <UFormField
                    v-if="hasCapacity"
                    :label="trans('bookings.resources.fields.capacity')"
                    :help="trans('bookings.resources.fields.capacity_help')"
                    name="capacity"
                    :error="form.errors.capacity"
                >
                    <UInput
                        v-model.number="form.capacity"
                        type="number"
                        min="1"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    v-if="hasCapacity"
                    :label="trans('bookings.resources.fields.combine_group')"
                    :help="
                        trans('bookings.resources.fields.combine_group_help')
                    "
                    name="combine_group"
                    :error="form.errors.combine_group"
                >
                    <UInput
                        v-model="form.combine_group"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>
            </div>

            <UFormField
                :label="trans('bookings.resources.fields.description')"
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

        <WorkingHoursFields
            v-model="form.working_hours"
            :errors="errors"
            :readonly="readonly"
        />

        <UPageCard
            variant="subtle"
            :title="trans('bookings.resources.sections.services')"
            :description="
                readonly
                    ? undefined
                    : trans('bookings.resources.sections.services_description')
            "
        >
            <p v-if="!services.length" class="text-muted text-sm">
                {{ trans('bookings.resources.services.empty') }}
            </p>

            <div class="divide-default flex flex-col divide-y">
                <div
                    v-for="service in services"
                    :key="service.value"
                    class="flex flex-col gap-3 py-3 first:pt-0 last:pb-0"
                >
                    <UCheckbox
                        :model-value="chosenIndex(service.value) !== -1"
                        :label="service.label"
                        :disabled="readonly"
                        @update:model-value="
                            (chosen) =>
                                toggleService(service.value, chosen === true)
                        "
                    />

                    <div
                        v-if="chosenIndex(service.value) !== -1"
                        class="grid grid-cols-1 gap-4 md:grid-cols-2"
                    >
                        <UFormField
                            :label="
                                trans('bookings.resources.services.price', {
                                    currency,
                                })
                            "
                            :name="`services.${chosenIndex(service.value)}.price`"
                            :error="
                                errors[
                                    `services.${chosenIndex(service.value)}.price`
                                ]
                            "
                        >
                            <UInput
                                v-model.nullable="
                                    form.services[chosenIndex(service.value)]
                                        .price
                                "
                                type="number"
                                min="0"
                                step="0.01"
                                :disabled="readonly"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            :label="
                                trans(
                                    'bookings.resources.services.duration_minutes',
                                )
                            "
                            :name="`services.${chosenIndex(service.value)}.duration_minutes`"
                            :error="
                                errors[
                                    `services.${chosenIndex(service.value)}.duration_minutes`
                                ]
                            "
                        >
                            <UInput
                                v-model.nullable="
                                    form.services[chosenIndex(service.value)]
                                        .duration_minutes
                                "
                                type="number"
                                min="1"
                                :disabled="readonly"
                                class="w-full"
                            />
                        </UFormField>
                    </div>
                </div>
            </div>
        </UPageCard>

        <template #sidebar>
            <UPageCard
                variant="subtle"
                :title="trans('bookings.resources.sections.visibility')"
            >
                <USwitch
                    v-model="form.is_active"
                    :disabled="readonly"
                    :label="trans('bookings.resources.fields.is_active')"
                    :description="
                        trans('bookings.resources.fields.is_active_help')
                    "
                />

                <USwitch
                    v-model="form.is_public"
                    :disabled="readonly"
                    :label="trans('bookings.resources.fields.is_public')"
                />

                <UFormField
                    v-if="hasLocations"
                    :label="trans('bookings.resources.fields.location')"
                    name="location_id"
                    :error="form.errors.location_id"
                >
                    <USelect
                        v-model="form.location_id"
                        :items="locationItems"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>
            </UPageCard>

            <UPageCard variant="subtle">
                <UFormField
                    :label="trans('bookings.resources.fields.image')"
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
                    :label="trans('bookings.resources.fields.slug')"
                    :help="trans('bookings.resources.fields.slug_help')"
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
