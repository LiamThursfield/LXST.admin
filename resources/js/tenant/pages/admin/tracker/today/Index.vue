<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import TodayController from '@/actions/App/Http/Controllers/Tenant/Admin/Tracker/TodayController';
import { addDays } from '@/lib/timezone';
import type { TaskOccurrenceData } from '@/types';
import OccurrenceCard from './components/OccurrenceCard.vue';

const { trans } = useTrans();

const props = defineProps<{
    /** The day being viewed (Y-m-d). */
    date: string;
    /** Today's date (Y-m-d), the latest day that can be viewed. */
    today: string;
    occurrences: { data: TaskOccurrenceData[] };
    canLog: boolean;
}>();

const isToday = computed(() => props.date === props.today);

const heading = computed(() =>
    isToday.value
        ? trans('tracker.today.title')
        : new Date(`${props.date}T12:00:00`).toLocaleDateString(undefined, {
              weekday: 'long',
              day: 'numeric',
              month: 'long',
          }),
);

/** Met vs. countable occurrences (skipped ones are excused). */
const summary = computed(() => {
    const countable = props.occurrences.data.filter(
        (occurrence) => occurrence.status !== 'skipped',
    );

    return {
        met: countable.filter((occurrence) => occurrence.status === 'met')
            .length,
        total: countable.length,
    };
});

function goTo(date: string) {
    const target = date > props.today ? props.today : date;

    router.get(
        TodayController.url(),
        target === props.today ? {} : { date: target },
        { preserveScroll: true },
    );
}

function shift(days: number) {
    goTo(addDays(props.date, days));
}
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="heading">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>

                <template #right>
                    <div class="flex items-center gap-2">
                        <UButton
                            color="neutral"
                            variant="ghost"
                            icon="i-lucide-chevron-left"
                            :aria-label="trans('tracker.today.previous_day')"
                            @click="shift(-1)"
                        />
                        <UInput
                            type="date"
                            :model-value="date"
                            :max="today"
                            class="w-40"
                            @update:model-value="
                                (value) => value && goTo(String(value))
                            "
                        />
                        <UButton
                            color="neutral"
                            variant="ghost"
                            icon="i-lucide-chevron-right"
                            :aria-label="trans('tracker.today.next_day')"
                            :disabled="isToday"
                            @click="shift(1)"
                        />
                        <UButton
                            v-if="!isToday"
                            color="neutral"
                            variant="subtle"
                            :label="trans('tracker.today.title')"
                            @click="goTo(today)"
                        />
                    </div>
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="heading" />

            <div class="flex flex-col gap-6">
                <p v-if="summary.total" class="text-muted text-sm">
                    {{
                        trans('tracker.today.summary', {
                            met: summary.met,
                            total: summary.total,
                        })
                    }}
                </p>

                <div
                    v-if="occurrences.data.length"
                    class="grid grid-cols-1 gap-4 lg:grid-cols-2"
                >
                    <OccurrenceCard
                        v-for="occurrence in occurrences.data"
                        :key="occurrence.id"
                        :occurrence="occurrence"
                        :date="date"
                        :is-today="isToday"
                        :can-log="canLog"
                    />
                </div>

                <UEmpty
                    v-else
                    icon="i-lucide-calendar-check"
                    :title="trans('tracker.today.empty_title')"
                    :description="trans('tracker.today.empty_description')"
                />
            </div>
        </template>
    </UDashboardPanel>
</template>
