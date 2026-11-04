<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    index,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/RotaController';
import type { RotaResource, SchedulePeriod } from '@/types';

defineProps<{
    week: string;
    previousWeek: string;
    nextWeek: string;
    days: Array<{ date: string; day_of_week: number }>;
    resources: RotaResource[];
    canManage: boolean;
}>();

const { trans } = useTrans();

function goTo(week?: string) {
    router.get(index.url(), week ? { week } : {}, { preserveScroll: true });
}

/** Short day and month, read from the date itself so it can't shift with the browser's timezone. */
function shortDate(date: string): string {
    const [year, month, day] = date.split('-').map(Number);

    return new Intl.DateTimeFormat(undefined, {
        day: 'numeric',
        month: 'short',
        timeZone: 'UTC',
    }).format(new Date(Date.UTC(year, month - 1, day)));
}

const editing = ref<{ resource: RotaResource; date: string } | null>(null);

const form = useForm({
    resource_id: 0,
    date: '',
    shifts: [] as SchedulePeriod[],
});

/** Server validation errors, including nested ones such as `shifts.0.starts_at`. */
const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

/** Starts from what's showing, so changing the usual hours for one date is a small edit. */
function edit(resource: RotaResource, date: string) {
    form.clearErrors();
    form.resource_id = resource.id;
    form.date = date;
    form.shifts = resource.schedule[date].periods.map((period) => ({
        ...period,
    }));
    editing.value = { resource, date };
}

function addShift() {
    form.shifts.push({ starts_at: '09:00', ends_at: '17:00', note: null });
}

function save() {
    form.submit(update(), {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    });
}
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('bookings.rota.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>

                <template #right>
                    <div class="flex items-center gap-2">
                        <UButton
                            color="neutral"
                            variant="ghost"
                            icon="i-lucide-chevron-left"
                            :aria-label="trans('bookings.rota.previous_week')"
                            @click="goTo(previousWeek)"
                        />
                        <UButton
                            color="neutral"
                            variant="subtle"
                            :label="trans('bookings.rota.this_week')"
                            @click="goTo()"
                        />
                        <UButton
                            color="neutral"
                            variant="ghost"
                            icon="i-lucide-chevron-right"
                            :aria-label="trans('bookings.rota.next_week')"
                            @click="goTo(nextWeek)"
                        />
                    </div>
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <p v-if="!resources.length" class="text-muted text-sm">
                {{ trans('bookings.rota.empty') }}
            </p>

            <div v-else class="overflow-x-auto">
                <table class="min-w-3xl w-full text-sm">
                    <thead>
                        <tr class="border-default border-b text-left">
                            <th class="p-2 font-medium">
                                {{ trans('bookings.rota.resource') }}
                            </th>
                            <th
                                v-for="day in days"
                                :key="day.date"
                                class="p-2 font-medium"
                            >
                                <div>
                                    {{
                                        trans(
                                            `crm.opening_hours.days.${day.day_of_week}`,
                                        )
                                    }}
                                </div>
                                <div class="text-muted font-normal">
                                    {{ shortDate(day.date) }}
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="resource in resources"
                            :key="resource.id"
                            class="border-default border-b align-top"
                        >
                            <td class="p-2">
                                <div class="font-medium">
                                    {{ resource.name }}
                                </div>
                                <div class="text-muted">
                                    {{ resource.type_label }}
                                </div>
                            </td>
                            <td v-for="day in days" :key="day.date" class="p-1">
                                <component
                                    :is="canManage ? 'button' : 'div'"
                                    :type="canManage ? 'button' : undefined"
                                    class="min-h-10 w-full rounded p-1 text-left"
                                    :class="{
                                        'hover:bg-elevated cursor-pointer':
                                            canManage,
                                    }"
                                    @click="
                                        canManage && edit(resource, day.date)
                                    "
                                >
                                    <UBadge
                                        v-if="
                                            resource.schedule[day.date]
                                                .source === 'off'
                                        "
                                        color="neutral"
                                        variant="subtle"
                                        :label="trans('bookings.rota.off')"
                                    />
                                    <span
                                        v-else-if="
                                            !resource.schedule[day.date].periods
                                                .length
                                        "
                                        class="text-muted"
                                    >
                                        {{ trans('bookings.rota.not_working') }}
                                    </span>
                                    <div
                                        v-for="(period, periodIndex) in resource
                                            .schedule[day.date].periods"
                                        :key="periodIndex"
                                        :class="{
                                            'text-primary font-medium':
                                                resource.schedule[day.date]
                                                    .source === 'rota',
                                        }"
                                        :title="
                                            resource.schedule[day.date]
                                                .source === 'rota'
                                                ? trans('bookings.rota.changed')
                                                : undefined
                                        "
                                    >
                                        {{ period.starts_at }}–{{
                                            period.ends_at
                                        }}
                                    </div>
                                </component>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <UModal
                :open="editing !== null"
                :title="
                    editing
                        ? trans('bookings.rota.edit_title', {
                              name: editing.resource.name,
                              date: shortDate(editing.date),
                          })
                        : ''
                "
                :description="trans('bookings.rota.edit_description')"
                @update:open="(open) => !open && (editing = null)"
            >
                <template #body>
                    <div class="flex flex-col gap-3">
                        <p v-if="errors.shifts" class="text-error text-sm">
                            {{ errors.shifts }}
                        </p>
                        <p
                            v-if="!form.shifts.length"
                            class="text-muted text-sm"
                        >
                            {{ trans('bookings.rota.no_shifts') }}
                        </p>

                        <div
                            v-for="(shift, shiftIndex) in form.shifts"
                            :key="shiftIndex"
                            class="flex flex-wrap items-start gap-2"
                        >
                            <UFormField
                                :label="
                                    trans('bookings.resources.fields.starts_at')
                                "
                                :error="
                                    errors[`shifts.${shiftIndex}.starts_at`]
                                "
                                class="w-28"
                            >
                                <UInput
                                    v-model="shift.starts_at"
                                    type="time"
                                    class="w-full"
                                />
                            </UFormField>
                            <UFormField
                                :label="
                                    trans('bookings.resources.fields.ends_at')
                                "
                                :error="errors[`shifts.${shiftIndex}.ends_at`]"
                                class="w-28"
                            >
                                <UInput
                                    v-model="shift.ends_at"
                                    type="time"
                                    class="w-full"
                                />
                            </UFormField>
                            <UFormField
                                :label="trans('bookings.rota.fields.note')"
                                :error="errors[`shifts.${shiftIndex}.note`]"
                                class="min-w-32 flex-1"
                            >
                                <UInput
                                    v-model.nullable="shift.note"
                                    class="w-full"
                                />
                            </UFormField>
                            <UButton
                                class="mt-6"
                                color="neutral"
                                variant="ghost"
                                icon="i-lucide-x"
                                :aria-label="
                                    trans('bookings.rota.remove_shift')
                                "
                                @click="form.shifts.splice(shiftIndex, 1)"
                            />
                        </div>

                        <div>
                            <UButton
                                color="neutral"
                                variant="subtle"
                                icon="i-lucide-plus"
                                :label="trans('bookings.rota.add_shift')"
                                @click="addShift"
                            />
                        </div>
                    </div>
                </template>

                <template #footer>
                    <div class="flex w-full justify-end gap-2">
                        <UButton
                            color="neutral"
                            variant="ghost"
                            :label="trans('common.actions.cancel')"
                            @click="editing = null"
                        />
                        <UButton
                            :label="trans('common.actions.save')"
                            :loading="form.processing"
                            @click="save"
                        />
                    </div>
                </template>
            </UModal>
        </template>
    </UDashboardPanel>
</template>
