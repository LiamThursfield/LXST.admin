<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import StaffHoursController from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/StaffHoursController';
import type { StaffHoursRow } from '@/types';

const props = defineProps<{
    from: string;
    to: string;
    people: StaffHoursRow[];
}>();

const { trans } = useTrans();

function change(field: 'from' | 'to', value: unknown) {
    if (value) {
        router.get(
            StaffHoursController.url(),
            { from: props.from, to: props.to, [field]: String(value) },
            { preserveScroll: true },
        );
    }
}
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('bookings.staff_hours.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>

                <template #right>
                    <div class="flex items-center gap-2">
                        <UInput
                            type="date"
                            class="w-40"
                            :model-value="from"
                            :aria-label="trans('bookings.staff_hours.from')"
                            @update:model-value="
                                (value) => change('from', value)
                            "
                        />
                        <UInput
                            type="date"
                            class="w-40"
                            :model-value="to"
                            :aria-label="trans('bookings.staff_hours.to')"
                            @update:model-value="(value) => change('to', value)"
                        />
                    </div>
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <p class="text-muted text-sm">
                {{ trans('bookings.staff_hours.description') }}
            </p>

            <p v-if="!people.length" class="text-muted text-sm">
                {{ trans('bookings.staff_hours.empty') }}
            </p>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-default border-b text-left">
                            <th class="p-2 font-medium">
                                {{ trans('bookings.staff_hours.name') }}
                            </th>
                            <th class="p-2 font-medium">
                                {{ trans('bookings.staff_hours.job_title') }}
                            </th>
                            <th class="p-2 text-right font-medium">
                                {{ trans('bookings.staff_hours.hours') }}
                            </th>
                            <th class="p-2 text-right font-medium">
                                {{ trans('bookings.staff_hours.days_off') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="person in people"
                            :key="person.id"
                            class="border-default border-b"
                        >
                            <td class="p-2">{{ person.name }}</td>
                            <td class="text-muted p-2">
                                {{ person.job_title }}
                            </td>
                            <td class="p-2 text-right">{{ person.hours }}</td>
                            <td class="p-2 text-right">
                                {{ person.days_off }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </UDashboardPanel>
</template>
