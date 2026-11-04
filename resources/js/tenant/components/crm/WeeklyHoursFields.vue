<script setup lang="ts">
import type { OpeningHourData } from '@/types';
import OpeningPeriodFields from './OpeningPeriodFields.vue';

/**
 * A location's weekly hours, a day at a time. The hours are submitted as one flat list, so each
 * row keeps its place in that list for its validation errors.
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

const hours = defineModel<OpeningHourData[]>({ required: true });

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
        opens_at: '09:00',
        closes_at: '17:00',
        label: null,
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
        :title="trans('crm.opening_hours.weekly_title')"
        :description="
            readonly ? undefined : trans('crm.opening_hours.weekly_description')
        "
    >
        <p v-if="errors.opening_hours" class="text-error text-sm">
            {{ errors.opening_hours }}
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
                            :label="trans('crm.opening_hours.copy_to_all')"
                            @click="copyToAll(day)"
                        />
                        <UButton
                            color="neutral"
                            variant="subtle"
                            size="xs"
                            icon="i-lucide-plus"
                            :label="trans('crm.opening_hours.add_period')"
                            @click="addPeriod(day)"
                        />
                    </div>
                </div>

                <p v-if="!rowsFor(day).length" class="text-muted text-sm">
                    {{ trans('crm.opening_hours.closed') }}
                </p>

                <OpeningPeriodFields
                    v-for="index in rowsFor(day)"
                    :key="index"
                    v-model="hours[index]"
                    :errors="errors"
                    :path="`opening_hours.${index}`"
                    :readonly="readonly"
                    @remove="hours.splice(index, 1)"
                />
            </div>
        </div>
    </UPageCard>
</template>
