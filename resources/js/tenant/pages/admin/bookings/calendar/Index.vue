<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import BookingCalendarController from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/BookingCalendarController';
import { move } from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/BookingController';
import type { CalendarColumn, CalendarItem, SelectItem } from '@/types';

const props = defineProps<{
    view: 'day' | 'week';
    date: string;
    previousDate: string;
    nextDate: string;
    timezone: string;
    columns: CalendarColumn[];
    startMinutes: number;
    endMinutes: number;
    slotMinutes: number;
    hasLocations: boolean;
    locationId: number | null;
    locations: SelectItem[];
    resourceId: number | null;
    resources: SelectItem[];
    canManage: boolean;
}>();

const { trans } = useTrans();

/** How tall a minute is, so a 15 minute slot is 12px. */
const PIXELS_PER_MINUTE = 0.8;

const height = computed(
    () => (props.endMinutes - props.startMinutes) * PIXELS_PER_MINUTE,
);

const hours = computed(() => {
    const list: number[] = [];

    for (
        let minute = props.startMinutes;
        minute < props.endMinutes;
        minute += 60
    ) {
        list.push(minute);
    }

    return list;
});

function top(minutes: number): string {
    return `${(minutes - props.startMinutes) * PIXELS_PER_MINUTE}px`;
}

function span(start: number, end: number): string {
    return `${Math.max(end - start, props.slotMinutes) * PIXELS_PER_MINUTE}px`;
}

function clock(minutes: number): string {
    const hour = Math.floor(minutes / 60) % 24;
    const minute = minutes % 60;

    return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
}

/** Day and month, read from the date itself so it can't shift with the browser's timezone. */
function shortDate(date: string): string {
    const [year, month, day] = date.split('-').map(Number);

    return new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        timeZone: 'UTC',
    }).format(new Date(Date.UTC(year, month - 1, day)));
}

function visit(query: Record<string, string | number | null | undefined>) {
    router.get(
        BookingCalendarController.url(),
        {
            view: props.view,
            date: props.date,
            location_id: props.locationId ?? undefined,
            resource_id: props.resourceId ?? undefined,
            ...query,
        },
        { preserveScroll: true },
    );
}

const locationItems = computed(() => [
    {
        value: null as unknown as number,
        label: trans('bookings.calendar.all_locations'),
    },
    ...props.locations,
]);

const viewItems = computed(() => [
    { value: 'day', label: trans('bookings.calendar.day') },
    { value: 'week', label: trans('bookings.calendar.week') },
]);

const dragging = ref<CalendarItem | null>(null);
const moveError = ref<string | null>(null);

function onDragStart(event: DragEvent, item: CalendarItem) {
    dragging.value = item;
    event.dataTransfer?.setData('text/plain', String(item.id));
}

/** Drops a booking at the slot under the pointer, in the column's resource and date. */
function onDrop(event: DragEvent, column: CalendarColumn) {
    const item = dragging.value;
    dragging.value = null;

    if (!item) {
        return;
    }

    const bounds = (event.currentTarget as HTMLElement).getBoundingClientRect();
    const offset = (event.clientY - bounds.top) / PIXELS_PER_MINUTE;
    const minutes =
        props.startMinutes +
        Math.round(offset / props.slotMinutes) * props.slotMinutes;

    moveError.value = null;

    router.put(
        move.url(item.id),
        {
            date: column.date,
            time: clock(Math.max(0, minutes)),
            resource_id: column.resource_id,
        },
        {
            preserveScroll: true,
            onError: (errors) => {
                moveError.value = Object.values(errors)[0] ?? null;
            },
        },
    );
}
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('bookings.calendar.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>

                <template #right>
                    <div class="flex flex-wrap items-center gap-2">
                        <USelect
                            :model-value="view"
                            :items="viewItems"
                            class="w-28"
                            @update:model-value="
                                (value) => visit({ view: value })
                            "
                        />
                        <USelect
                            v-if="view === 'week'"
                            :model-value="resourceId ?? undefined"
                            :items="resources"
                            :aria-label="trans('bookings.calendar.resource')"
                            class="w-44"
                            @update:model-value="
                                (value) => visit({ resource_id: value })
                            "
                        />
                        <USelect
                            v-if="hasLocations"
                            :model-value="
                                locationId ?? (null as unknown as number)
                            "
                            :items="locationItems"
                            :aria-label="trans('bookings.calendar.location')"
                            class="w-44"
                            @update:model-value="
                                (value) => visit({ location_id: value })
                            "
                        />
                        <UButton
                            color="neutral"
                            variant="ghost"
                            icon="i-lucide-chevron-left"
                            :aria-label="trans('bookings.calendar.previous')"
                            @click="visit({ date: previousDate })"
                        />
                        <UButton
                            color="neutral"
                            variant="subtle"
                            :label="trans('bookings.calendar.today')"
                            @click="visit({ date: undefined })"
                        />
                        <UButton
                            color="neutral"
                            variant="ghost"
                            icon="i-lucide-chevron-right"
                            :aria-label="trans('bookings.calendar.next')"
                            @click="visit({ date: nextDate })"
                        />
                    </div>
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <div class="flex flex-col gap-2">
                <p class="text-muted text-sm">
                    {{ shortDate(date) }} · {{ timezone }}
                    <template v-if="canManage">
                        · {{ trans('bookings.calendar.drag_help') }}
                    </template>
                </p>

                <UAlert
                    v-if="moveError"
                    color="error"
                    variant="subtle"
                    :title="trans('bookings.calendar.move_failed')"
                    :description="moveError"
                    close
                    @update:open="moveError = null"
                />
            </div>

            <p v-if="!columns.length" class="text-muted text-sm">
                {{ trans('bookings.calendar.empty') }}
            </p>

            <div v-else class="overflow-x-auto">
                <div class="flex min-w-max">
                    <div class="w-14 shrink-0 pt-12">
                        <div
                            class="relative"
                            :style="{ height: `${height}px` }"
                        >
                            <div
                                v-for="hour in hours"
                                :key="hour"
                                class="text-muted absolute -translate-y-2 text-xs"
                                :style="{ top: top(hour) }"
                            >
                                {{ clock(hour) }}
                            </div>
                        </div>
                    </div>

                    <div
                        v-for="column in columns"
                        :key="column.key"
                        class="border-default w-44 shrink-0 border-l"
                    >
                        <div class="h-12 px-2 text-sm">
                            <div class="truncate font-medium">
                                {{
                                    view === 'week'
                                        ? shortDate(column.date)
                                        : column.resource_name
                                }}
                            </div>
                            <div class="text-muted truncate text-xs">
                                {{
                                    column.off
                                        ? trans('bookings.calendar.off')
                                        : view === 'week'
                                          ? column.resource_name
                                          : column.type_label
                                }}
                            </div>
                        </div>

                        <div
                            class="relative"
                            :class="
                                column.follows_location && !column.off
                                    ? 'bg-default'
                                    : 'bg-elevated/40'
                            "
                            :style="{ height: `${height}px` }"
                            @dragover.prevent
                            @drop.prevent="onDrop($event, column)"
                        >
                            <div
                                v-for="hour in hours"
                                :key="hour"
                                class="border-default absolute inset-x-0 border-t"
                                :style="{ top: top(hour) }"
                            />
                            <div
                                v-for="(period, index) in column.periods"
                                :key="index"
                                class="bg-default absolute inset-x-0"
                                :style="{
                                    top: top(period.start),
                                    height: span(period.start, period.end),
                                }"
                            />

                            <component
                                :is="item.movable && canManage ? 'div' : Link"
                                v-for="item in column.items"
                                :key="`${item.kind}-${item.id}`"
                                :href="
                                    item.movable && canManage
                                        ? undefined
                                        : item.url
                                "
                                :draggable="item.movable && canManage"
                                class="absolute inset-x-1 overflow-hidden rounded border px-1.5 py-0.5 text-xs"
                                :class="
                                    item.kind === 'session'
                                        ? 'border-default bg-accented'
                                        : 'border-primary/40 bg-primary/15 cursor-grab'
                                "
                                :style="{
                                    top: top(item.start),
                                    height: span(item.start, item.end),
                                }"
                                @dragstart="onDragStart($event, item)"
                                @dblclick="router.visit(item.url)"
                            >
                                <div class="truncate font-medium">
                                    {{ clock(item.start) }} {{ item.title }}
                                </div>
                                <div class="text-muted truncate">
                                    {{
                                        item.kind === 'session'
                                            ? trans(
                                                  'bookings.calendar.session',
                                                  {
                                                      count: item.booked ?? 0,
                                                  },
                                              )
                                            : item.subtitle
                                    }}
                                </div>
                            </component>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </UDashboardPanel>
</template>
