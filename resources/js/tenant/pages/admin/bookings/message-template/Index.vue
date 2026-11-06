<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { edit } from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/MessageTemplateController';

defineProps<{
    events: Array<{
        value: string;
        label: string;
        description: string;
        is_enabled: boolean;
        offset_hours: number | null;
        is_scheduled: boolean;
        has_whatsapp_template: boolean;
    }>;
    canSendSms: boolean;
    canSendWhatsApp: boolean;
}>();

const { trans, transChoice } = useTrans();
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('bookings.messages.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <p class="text-muted text-sm">
                {{ trans('bookings.messages.description') }}
            </p>

            <UAlert
                v-if="!canSendSms"
                color="neutral"
                variant="subtle"
                icon="i-lucide-info"
                :title="trans('bookings.messages.sms_not_set_up')"
            />

            <div class="flex flex-col gap-3">
                <Link
                    v-for="event in events"
                    :key="event.value"
                    :href="edit.url(event.value)"
                    class="border-default hover:bg-elevated flex items-center justify-between gap-4 rounded-lg border p-4"
                >
                    <div>
                        <div class="font-medium">{{ event.label }}</div>
                        <div class="text-muted text-sm">
                            {{ event.description }}
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <UBadge
                            v-if="event.is_scheduled && event.offset_hours"
                            color="neutral"
                            variant="outline"
                            :label="
                                transChoice(
                                    event.value === 'reminder'
                                        ? 'bookings.messages.hours_before'
                                        : 'bookings.messages.hours_after',
                                    event.offset_hours,
                                    { count: event.offset_hours },
                                )
                            "
                        />
                        <UBadge
                            v-if="
                                canSendWhatsApp && event.has_whatsapp_template
                            "
                            color="neutral"
                            variant="outline"
                            :label="trans('bookings.messages.whatsapp_ready')"
                        />
                        <UBadge
                            :color="event.is_enabled ? 'success' : 'neutral'"
                            variant="subtle"
                            :label="
                                trans(
                                    event.is_enabled
                                        ? 'bookings.messages.on'
                                        : 'bookings.messages.off',
                                )
                            "
                        />
                        <UIcon
                            name="i-lucide-chevron-right"
                            class="text-muted"
                        />
                    </div>
                </Link>
            </div>
        </template>
    </UDashboardPanel>
</template>
