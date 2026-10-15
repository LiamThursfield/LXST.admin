<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import TodayController from '@/actions/App/Http/Controllers/Tenant/Admin/Tracker/TodayController';
import type { TaskOccurrenceData } from '@/types';
import OccurrenceCard from './components/OccurrenceCard.vue';

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
        ? 'Today'
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
    const day = new Date(`${props.date}T12:00:00`);
    day.setDate(day.getDate() + days);

    goTo(day.toISOString().slice(0, 10));
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
                            aria-label="Previous day"
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
                            aria-label="Next day"
                            :disabled="isToday"
                            @click="shift(1)"
                        />
                        <UButton
                            v-if="!isToday"
                            color="neutral"
                            variant="subtle"
                            label="Today"
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
                    {{ summary.met }} of {{ summary.total }} met
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
                    title="Nothing scheduled"
                    description="No tasks are due on this day."
                />
            </div>
        </template>
    </UDashboardPanel>
</template>
