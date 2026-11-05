<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    index,
    schedule,
    status as updateStatus,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/BookingController';
import type { BookingData, SelectItem } from '@/types';

const props = defineProps<{
    booking: { data: BookingData };
    statuses: SelectItem[];
    currency: string;
    canManage: boolean;
    resources: SelectItem[];
}>();

const { trans } = useTrans();

const booking = computed(() => props.booking.data);

const price = computed(() =>
    booking.value.price === null
        ? null
        : new Intl.NumberFormat(undefined, {
              style: 'currency',
              currency: props.currency,
          }).format(Number(booking.value.price)),
);

const breadcrumbs = computed(() => [
    { label: trans('bookings.bookings.index.title'), to: index.url() },
    { label: booking.value.customer_name },
]);

function move(status: string) {
    router.post(
        updateStatus.url(booking.value.id),
        { status },
        { preserveScroll: true },
    );
}

/** Setting a time is how an enquiry moves on, so it isn't offered as a plain status. */
const moves = computed(() =>
    booking.value.needs_time
        ? booking.value.next_statuses.filter((next) => next === 'cancelled')
        : booking.value.next_statuses,
);

const setTimeOpen = ref(false);

const form = useForm({
    date: '',
    time: '',
    duration_minutes: 60,
    resource_ids: [] as number[],
});

/** Server validation errors, including nested ones such as `resource_ids.0`. */
const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

function setTime() {
    form.submit(schedule(booking.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            setTimeOpen.value = false;
        },
    });
}
</script>

<template>
    <EditorLayout
        :title="trans('bookings.bookings.show.title')"
        :breadcrumbs="breadcrumbs"
        readonly
        narrow
    >
        <UPageCard
            variant="subtle"
            :title="trans('bookings.bookings.sections.status')"
        >
            <div class="flex flex-wrap items-center gap-2">
                <UBadge
                    color="neutral"
                    variant="subtle"
                    :label="booking.status_label"
                />
                <template v-if="canManage">
                    <UButton
                        v-if="
                            booking.needs_time && booking.status !== 'cancelled'
                        "
                        icon="i-lucide-calendar-plus"
                        :label="trans('bookings.bookings.actions.set_time')"
                        @click="setTimeOpen = true"
                    />
                    <UButton
                        v-for="next in moves"
                        :key="next"
                        :color="next === 'cancelled' ? 'error' : 'neutral'"
                        variant="subtle"
                        :label="trans(`bookings.bookings.actions.${next}`)"
                        @click="move(next)"
                    />
                </template>
            </div>
        </UPageCard>

        <UPageCard
            variant="subtle"
            :title="trans('bookings.bookings.sections.what')"
        >
            <dl class="grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
                <div>
                    <dt class="font-medium">
                        {{ trans('bookings.bookings.fields.service') }}
                    </dt>
                    <dd class="text-muted">{{ booking.service_name }}</dd>
                </div>
                <div>
                    <dt class="font-medium">
                        {{ trans('bookings.bookings.sections.when') }}
                    </dt>
                    <dd class="text-muted">
                        <template v-if="booking.when">
                            {{ booking.when }} – {{ booking.ends }} ({{
                                booking.timezone
                            }})
                        </template>
                        <template v-else>
                            {{ trans('bookings.bookings.fields.no_time_yet') }}
                        </template>
                    </dd>
                </div>
                <div v-if="booking.location_name">
                    <dt class="font-medium">
                        {{ trans('bookings.bookings.fields.location') }}
                    </dt>
                    <dd class="text-muted">{{ booking.location_name }}</dd>
                </div>
                <div v-if="booking.resource_names?.length">
                    <dt class="font-medium">
                        {{ trans('bookings.bookings.fields.resources') }}
                    </dt>
                    <dd class="text-muted">
                        {{ booking.resource_names.join(', ') }}
                    </dd>
                </div>
                <div v-if="booking.options?.length">
                    <dt class="font-medium">
                        {{ trans('bookings.bookings.fields.options') }}
                    </dt>
                    <dd class="text-muted">{{ booking.options.join(', ') }}</dd>
                </div>
                <div v-if="booking.price !== null">
                    <dt class="font-medium">
                        {{ trans('bookings.bookings.fields.price') }}
                    </dt>
                    <dd class="text-muted">{{ price }}</dd>
                </div>
            </dl>
        </UPageCard>

        <UPageCard
            variant="subtle"
            :title="trans('bookings.bookings.sections.customer')"
        >
            <dl class="grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
                <div>
                    <dt class="font-medium">
                        {{ trans('bookings.bookings.fields.customer_name') }}
                    </dt>
                    <dd class="text-muted">{{ booking.customer_name }}</dd>
                </div>
                <div v-if="booking.customer_email">
                    <dt class="font-medium">
                        {{ trans('bookings.bookings.fields.customer_email') }}
                    </dt>
                    <dd class="text-muted">{{ booking.customer_email }}</dd>
                </div>
                <div v-if="booking.customer_phone">
                    <dt class="font-medium">
                        {{ trans('bookings.bookings.fields.customer_phone') }}
                    </dt>
                    <dd class="text-muted">{{ booking.customer_phone }}</dd>
                </div>
                <div v-if="booking.notes" class="md:col-span-2">
                    <dt class="font-medium">
                        {{ trans('bookings.bookings.fields.notes') }}
                    </dt>
                    <dd class="text-muted whitespace-pre-line">
                        {{ booking.notes }}
                    </dd>
                </div>
            </dl>
        </UPageCard>

        <UModal
            v-model:open="setTimeOpen"
            :title="trans('bookings.bookings.set_time.title')"
            :description="trans('bookings.bookings.set_time.description')"
        >
            <template #body>
                <div class="flex flex-col gap-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <UFormField
                            :label="trans('bookings.bookings.fields.date')"
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
                            :error="form.errors.time"
                            required
                        >
                            <UInput
                                v-model="form.time"
                                type="time"
                                class="w-full"
                            />
                        </UFormField>
                        <UFormField
                            :label="
                                trans(
                                    'bookings.bookings.fields.duration_minutes',
                                )
                            "
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
                        :label="trans('bookings.bookings.fields.resources')"
                        :error="errors.resource_ids"
                    >
                        <USelectMenu
                            v-model="form.resource_ids"
                            :items="resources"
                            value-key="value"
                            multiple
                            class="w-full"
                        />
                    </UFormField>
                </div>
            </template>

            <template #footer>
                <div class="flex w-full justify-end gap-2">
                    <UButton
                        color="neutral"
                        variant="ghost"
                        :label="trans('common.actions.cancel')"
                        @click="setTimeOpen = false"
                    />
                    <UButton
                        :label="trans('common.actions.save')"
                        :loading="form.processing"
                        @click="setTime"
                    />
                </div>
            </template>
        </UModal>
    </EditorLayout>
</template>
