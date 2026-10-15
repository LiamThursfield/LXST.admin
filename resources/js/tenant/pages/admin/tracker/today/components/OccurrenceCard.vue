<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    destroy,
    store,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Tracker/TaskEntryController';
import { userTimezone } from '@/lib/timezone';
import {
    formatTimeOfDay,
    formatValue,
    occurrenceStatusBadge,
    parseTimeOfDay,
} from '@/lib/tracker';
import type { TaskOccurrenceData } from '@/types';

const { trans } = useTrans();

const props = defineProps<{
    occurrence: TaskOccurrenceData;
    /** The day being viewed (Y-m-d). */
    date: string;
    isToday: boolean;
    canLog: boolean;
}>();

const definition = computed(() => props.occurrence.definition);
const isBoolean = computed(() => definition.value.comparator === 'boolean');
const isTimeOfDay = computed(() => definition.value.unit === 'time-of-day');
const isAdditive = computed(() =>
    ['sum', 'count'].includes(definition.value.aggregation),
);
const status = computed(() => occurrenceStatusBadge(props.occurrence.status));

/** Periods other than daily are labelled, e.g. "This week". */
const labelledPeriods = ['weekly', 'monthly', 'yearly', 'lifetime', 'one-off'];
const periodLabel = computed(() =>
    labelledPeriods.includes(definition.value.period)
        ? trans(`tracker.today.periods.${definition.value.period}`)
        : null,
);

/** Current value, treating an empty additive window as zero. */
const currentValue = computed(
    () => props.occurrence.computed_value ?? (isAdditive.value ? 0 : null),
);

const progress = computed(() => {
    const unit = definition.value.unit;
    const current = formatValue(currentValue.value, unit);
    const min = formatValue(definition.value.target_min, unit);
    const max = formatValue(definition.value.target_max, unit);

    switch (definition.value.comparator) {
        case 'min':
            return trans('tracker.today.progress.min', { current, min });
        case 'max':
            return trans('tracker.today.progress.max', { current, max });
        case 'between':
            return trans('tracker.today.progress.between', {
                current,
                min,
                max,
            });
        case 'none':
            return current;
        default:
            return null;
    }
});

const amount = ref<number | null>(null);
const time = ref('');

const form = useForm({
    value: null as number | null,
    occurred_at: null as string | null,
});

function log(value: number) {
    form.value = value;
    // Today logs "now"; a past day is backfilled at midday, local time.
    form.occurred_at = props.isToday ? null : `${props.date} 12:00`;

    form.submit(store({ task: definition.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            amount.value = null;
            time.value = '';
            form.reset();
        },
    });
}

function logInput() {
    if (isTimeOfDay.value) {
        if (time.value) {
            log(parseTimeOfDay(time.value));
        }

        return;
    }

    if (amount.value !== null && amount.value >= 0) {
        log(amount.value);
    }
}

function removeEntry(entryId: number) {
    router.delete(destroy.url({ entry: entryId }), { preserveScroll: true });
}

function undoDone() {
    const last = props.occurrence.entries.at(-1);

    if (last) {
        removeEntry(last.id);
    }
}

const auth = useAuth();

/** Entries are stored in UTC; show them on the user's own clock. */
function entryTime(occurredAt: string): string {
    return new Date(occurredAt).toLocaleTimeString(undefined, {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: userTimezone(auth.value.user),
    });
}

function entryValue(value: number): string {
    return isTimeOfDay.value
        ? formatTimeOfDay(value)
        : formatValue(value, definition.value.unit);
}
</script>

<template>
    <UCard>
        <div class="flex flex-col gap-4">
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 flex-col gap-1">
                    <div class="flex items-center gap-2">
                        <UIcon
                            v-if="definition.category?.icon"
                            :name="definition.category.icon"
                            class="shrink-0"
                            :style="
                                definition.category.colour
                                    ? { color: definition.category.colour }
                                    : undefined
                            "
                        />
                        <span class="truncate font-medium">
                            {{ definition.title }}
                        </span>
                    </div>
                    <div
                        class="text-muted flex flex-wrap items-center gap-2 text-sm"
                    >
                        <span v-if="periodLabel">{{ periodLabel }}</span>
                        <span v-if="progress">{{ progress }}</span>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <UBadge
                        v-if="occurrence.is_holiday"
                        color="warning"
                        variant="outline"
                        icon="i-lucide-palmtree"
                    >
                        {{ trans('tracker.today.holiday') }}
                    </UBadge>
                    <UBadge :color="status.color" variant="subtle">
                        {{ status.label }}
                    </UBadge>
                </div>
            </div>

            <template v-if="canLog">
                <div v-if="isBoolean" class="flex gap-2">
                    <UButton
                        v-if="occurrence.status !== 'met'"
                        :loading="form.processing"
                        icon="i-lucide-check"
                        :label="trans('tracker.today.mark_done')"
                        @click="log(1)"
                    />
                    <UButton
                        v-else
                        color="neutral"
                        variant="subtle"
                        icon="i-lucide-undo-2"
                        :label="trans('tracker.today.undo')"
                        @click="undoDone"
                    />
                </div>

                <form v-else class="flex gap-2" @submit.prevent="logInput">
                    <UInput
                        v-if="isTimeOfDay"
                        v-model="time"
                        type="time"
                        class="w-40"
                    />
                    <UInput
                        v-else
                        v-model.number="amount"
                        type="number"
                        min="0"
                        step="any"
                        :placeholder="
                            definition.stepped
                                ? trans('tracker.today.add_amount')
                                : trans('tracker.today.value')
                        "
                        class="w-40"
                    />
                    <UButton
                        type="submit"
                        :loading="form.processing"
                        :label="
                            definition.stepped
                                ? trans('tracker.today.add')
                                : trans('common.actions.save')
                        "
                    />
                </form>

                <p
                    v-if="form.errors.value || form.errors.occurred_at"
                    class="text-error text-sm"
                >
                    {{ form.errors.value || form.errors.occurred_at }}
                </p>
            </template>

            <ul
                v-if="!isBoolean && occurrence.entries.length"
                class="divide-default divide-y text-sm"
            >
                <li
                    v-for="entry in occurrence.entries"
                    :key="entry.id"
                    class="flex items-center justify-between gap-2 py-1.5"
                >
                    <span class="text-muted">
                        {{ entryTime(entry.occurred_at) }}
                    </span>
                    <span class="flex-1">
                        {{ entryValue(entry.value) }}
                        <span v-if="entry.label" class="text-muted">
                            · {{ entry.label }}
                        </span>
                    </span>
                    <UButton
                        v-if="canLog"
                        color="neutral"
                        variant="ghost"
                        size="xs"
                        icon="i-lucide-x"
                        :aria-label="trans('tracker.today.remove_entry')"
                        @click="removeEntry(entry.id)"
                    />
                </li>
            </ul>
        </div>
    </UCard>
</template>
