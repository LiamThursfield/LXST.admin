<script setup lang="ts">
import type { WorkingHourData } from '@/types';

/**
 * A resource's usual week, a day at a time. The hours are submitted as one flat list, so each row
 * keeps its place in that list for its validation errors.
 */
withDefaults(
    defineProps<{
        /** Server validation errors, keyed by the full field name. */
        errors?: Record<string, string | undefined>;
        readonly?: boolean;
    }>(),
    {
        errors: () => ({}),
        readonly: false,
    },
);

const hours = defineModel<WorkingHourData[]>({ required: true });

const { trans } = useTrans();

const days = [1, 2, 3, 4, 5, 6, 7];

function rowsFor(day: number): number[] {
    return hours.value
        .map((_, index) => index)
        .filter((index) => hours.value[index].day_of_week === day);
}

function addPeriod(day: number) {
    hours.value.push({
        day_of_week: day,
        starts_at: '09:00',
        ends_at: '17:00',
    });
}

/** Saves filling in the same hours seven times. */
function copyToAll(day: number) {
    const periods = hours.value.filter((hour) => hour.day_of_week === day);

    hours.value = days.flatMap((target) =>
        periods.map((period) => ({ ...period, day_of_week: target })),
    );
}
</script>

<template>
    <UPageCard
        variant="subtle"
        :title="trans('bookings.resources.sections.working_hours')"
        :description="
            readonly
                ? undefined
                : trans('bookings.resources.sections.working_hours_description')
        "
    >
        <p v-if="errors.working_hours" class="text-error text-sm">
            {{ errors.working_hours }}
        </p>

        <div class="divide-default flex flex-col divide-y">
            <div
                v-for="day in days"
                :key="day"
                class="flex flex-col gap-2 py-3 first:pt-0 last:pb-0"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-sm font-medium">
                        {{ trans(`crm.opening_hours.days.${day}`) }}
                    </span>
                    <div v-if="!readonly" class="flex gap-1">
                        <UButton
                            v-if="rowsFor(day).length"
                            color="neutral"
                            variant="ghost"
                            size="xs"
                            icon="i-lucide-copy"
                            :label="
                                trans('bookings.resources.fields.copy_to_all')
                            "
                            @click="copyToAll(day)"
                        />
                        <UButton
                            color="neutral"
                            variant="subtle"
                            size="xs"
                            icon="i-lucide-plus"
                            :label="
                                trans('bookings.resources.fields.add_hours')
                            "
                            @click="addPeriod(day)"
                        />
                    </div>
                </div>

                <p v-if="!rowsFor(day).length" class="text-muted text-sm">
                    {{ trans('bookings.resources.fields.no_hours') }}
                </p>

                <div
                    v-for="index in rowsFor(day)"
                    :key="index"
                    class="flex flex-wrap items-start gap-2"
                >
                    <UFormField
                        :label="trans('bookings.resources.fields.starts_at')"
                        :name="`working_hours.${index}.starts_at`"
                        :error="errors[`working_hours.${index}.starts_at`]"
                        class="w-32"
                    >
                        <UInput
                            v-model="hours[index].starts_at"
                            :disabled="readonly"
                            class="w-full"
                            type="time"
                        />
                    </UFormField>
                    <UFormField
                        :label="trans('bookings.resources.fields.ends_at')"
                        :name="`working_hours.${index}.ends_at`"
                        :error="errors[`working_hours.${index}.ends_at`]"
                        class="w-32"
                    >
                        <UInput
                            v-model="hours[index].ends_at"
                            :disabled="readonly"
                            class="w-full"
                            type="time"
                        />
                    </UFormField>
                    <UButton
                        v-if="!readonly"
                        class="mt-6"
                        color="neutral"
                        variant="ghost"
                        icon="i-lucide-x"
                        :aria-label="
                            trans('bookings.resources.fields.remove_hours')
                        "
                        @click="hours.splice(index, 1)"
                    />
                </div>
            </div>
        </div>
    </UPageCard>
</template>
