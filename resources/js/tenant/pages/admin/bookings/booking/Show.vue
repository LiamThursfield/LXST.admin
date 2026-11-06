<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    index,
    message,
    schedule,
    status as updateStatus,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/BookingController';
import BookingPaymentController from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/BookingPaymentController';
import type { BookingData, BookingPaymentSummary, SelectItem } from '@/types';

const props = defineProps<{
    booking: { data: BookingData };
    statuses: SelectItem[];
    currency: string;
    canManage: boolean;
    resources: SelectItem[];
    messagePresets: string[];
    payments: BookingPaymentSummary | null;
    series: Array<{
        id: number;
        when: string | null;
        status_label: string;
        url: string;
        current: boolean;
    }>;
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

const requestOpen = ref(false);
const recordOpen = ref(false);

const requestForm = useForm({ amount: props.payments?.suggested ?? '' });
const recordForm = useForm({
    amount: props.payments?.suggested ?? '',
    method: 'cash',
    note: '',
});

function requestPayment() {
    requestForm.post(BookingPaymentController.store.url(booking.value.id), {
        preserveScroll: true,
        onSuccess: () => (requestOpen.value = false),
    });
}

function recordPayment() {
    recordForm.post(BookingPaymentController.record.url(booking.value.id), {
        preserveScroll: true,
        onSuccess: () => (recordOpen.value = false),
    });
}

const toast = useToast();

async function copyLink(link: string) {
    await navigator.clipboard.writeText(link);
    toast.add({ title: trans('bookings.payments.link_copied') });
}

const messageOpen = ref(false);

const messageForm = useForm({ body: '' });

const presetItems = computed(() =>
    props.messagePresets.map((preset) => ({ value: preset, label: preset })),
);

function sendMessage() {
    messageForm.submit(message(booking.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            messageOpen.value = false;
            messageForm.reset();
        },
    });
}

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
                <UBadge
                    v-if="booking.is_late_cancellation"
                    color="warning"
                    variant="subtle"
                    :label="trans('bookings.bookings.late_cancellation')"
                />
                <UBadge
                    v-if="booking.rules_ignored"
                    color="neutral"
                    variant="outline"
                    :label="trans('bookings.bookings.ignore_rules.badge')"
                />
                <UBadge
                    v-if="booking.is_walk_in"
                    color="neutral"
                    variant="outline"
                    :label="trans('bookings.bookings.walk_in.badge')"
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

        <UPageCard
            v-if="payments"
            variant="subtle"
            :title="trans('bookings.payments.section')"
        >
            <dl class="grid grid-cols-3 gap-3 text-sm">
                <div>
                    <dt class="font-medium">
                        {{ trans('bookings.payments.price') }}
                    </dt>
                    <dd class="text-muted">{{ payments.price ?? '–' }}</dd>
                </div>
                <div>
                    <dt class="font-medium">
                        {{ trans('bookings.payments.paid') }}
                    </dt>
                    <dd class="text-muted">{{ payments.paid }}</dd>
                </div>
                <div>
                    <dt class="font-medium">
                        {{ trans('bookings.payments.outstanding') }}
                    </dt>
                    <dd class="text-muted">
                        {{ payments.outstanding ?? '–' }}
                    </dd>
                </div>
            </dl>

            <p v-if="payments.price === null" class="text-muted text-sm">
                {{ trans('bookings.payments.on_quote') }}
            </p>

            <p v-if="!payments.list.length" class="text-muted text-sm">
                {{ trans('bookings.payments.none') }}
            </p>
            <ul v-else class="flex flex-col gap-2 text-sm">
                <li
                    v-for="payment in payments.list"
                    :key="payment.id"
                    class="flex flex-wrap items-center gap-2"
                >
                    <span class="font-medium">{{ payment.amount }}</span>
                    <span class="text-muted">
                        {{ payment.method_label }} · {{ payment.when }}
                    </span>
                    <UBadge
                        :color="
                            payment.status === 'paid' ? 'success' : 'neutral'
                        "
                        variant="subtle"
                        :label="payment.status_label"
                    />
                    <UButton
                        v-if="payment.link"
                        size="xs"
                        color="neutral"
                        variant="ghost"
                        icon="i-lucide-copy"
                        :label="trans('bookings.payments.copy_link')"
                        @click="copyLink(payment.link)"
                    />
                </li>
            </ul>

            <div
                v-if="payments.canManage && !payments.settled"
                class="flex flex-wrap gap-2"
            >
                <UButton
                    v-if="payments.canRequest"
                    icon="i-lucide-link"
                    :label="trans('bookings.payments.request')"
                    @click="requestOpen = true"
                />
                <UButton
                    color="neutral"
                    variant="subtle"
                    icon="i-lucide-banknote"
                    :label="trans('bookings.payments.record')"
                    @click="recordOpen = true"
                />
            </div>
            <p
                v-if="payments.canManage && !payments.canRequest"
                class="text-muted text-sm"
            >
                {{ trans('bookings.payments.not_set_up') }}
            </p>
        </UPageCard>

        <UModal
            v-model:open="requestOpen"
            :title="trans('bookings.payments.request')"
            :description="trans('bookings.payments.request_help')"
        >
            <template #body>
                <UFormField
                    :label="
                        trans('bookings.payments.amount', { currency: 'GBP' })
                    "
                    :error="requestForm.errors.amount"
                    required
                >
                    <UInput
                        v-model="requestForm.amount"
                        type="number"
                        min="0.30"
                        step="0.01"
                        class="w-full"
                    />
                </UFormField>
            </template>
            <template #footer>
                <div class="flex w-full justify-end gap-2">
                    <UButton
                        color="neutral"
                        variant="ghost"
                        :label="trans('common.actions.cancel')"
                        @click="requestOpen = false"
                    />
                    <UButton
                        icon="i-lucide-send"
                        :label="trans('bookings.payments.request')"
                        :loading="requestForm.processing"
                        @click="requestPayment"
                    />
                </div>
            </template>
        </UModal>

        <UModal
            v-model:open="recordOpen"
            :title="trans('bookings.payments.record')"
            :description="trans('bookings.payments.record_help')"
        >
            <template #body>
                <div class="flex flex-col gap-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <UFormField
                            :label="
                                trans('bookings.payments.amount', {
                                    currency: 'GBP',
                                })
                            "
                            :error="recordForm.errors.amount"
                            required
                        >
                            <UInput
                                v-model="recordForm.amount"
                                type="number"
                                min="0.01"
                                step="0.01"
                                class="w-full"
                            />
                        </UFormField>
                        <UFormField
                            :label="trans('bookings.payments.method')"
                            :error="recordForm.errors.method"
                        >
                            <USelect
                                v-model="recordForm.method"
                                :items="payments?.methods ?? []"
                                class="w-full"
                            />
                        </UFormField>
                    </div>
                    <UFormField
                        :label="trans('bookings.payments.note')"
                        :error="recordForm.errors.note"
                    >
                        <UInput v-model="recordForm.note" class="w-full" />
                    </UFormField>
                </div>
            </template>
            <template #footer>
                <div class="flex w-full justify-end gap-2">
                    <UButton
                        color="neutral"
                        variant="ghost"
                        :label="trans('common.actions.cancel')"
                        @click="recordOpen = false"
                    />
                    <UButton
                        :label="trans('common.actions.save')"
                        :loading="recordForm.processing"
                        @click="recordPayment"
                    />
                </div>
            </template>
        </UModal>

        <UPageCard
            variant="subtle"
            :title="trans('bookings.bookings.messages_title')"
            :description="booking.channel_label"
        >
            <div v-if="canManage">
                <UButton
                    color="neutral"
                    variant="subtle"
                    icon="i-lucide-message-square-plus"
                    :label="trans('bookings.messages.custom.button')"
                    @click="messageOpen = true"
                />
            </div>
            <p v-if="!booking.messages?.length" class="text-muted text-sm">
                {{ trans('bookings.bookings.no_messages') }}
            </p>
            <ul v-else class="flex flex-col gap-2 text-sm">
                <li v-for="message in booking.messages" :key="message.id">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">{{
                            message.event_label
                        }}</span>
                        <span class="text-muted">
                            {{ message.channel_label }}
                            <template v-if="message.recipient">
                                · {{ message.recipient }}
                            </template>
                            · {{ message.when }}
                        </span>
                        <UBadge
                            :color="
                                message.status === 'failed'
                                    ? 'error'
                                    : message.status === 'sent'
                                      ? 'success'
                                      : 'neutral'
                            "
                            variant="subtle"
                            :label="message.status_label"
                        />
                    </div>
                    <p v-if="message.note" class="text-muted">
                        {{ message.note }}
                    </p>
                </li>
            </ul>
        </UPageCard>

        <UPageCard
            v-if="series.length"
            variant="subtle"
            :title="trans('bookings.bookings.series.title')"
        >
            <ul class="flex flex-col gap-1 text-sm">
                <li
                    v-for="other in series"
                    :key="other.id"
                    class="flex justify-between gap-2"
                >
                    <span v-if="other.current" class="font-medium">
                        {{ other.when }}
                    </span>
                    <Link v-else :href="other.url" class="text-primary">
                        {{ other.when }}
                    </Link>
                    <span class="text-muted">{{ other.status_label }}</span>
                </li>
            </ul>
        </UPageCard>

        <UModal
            v-model:open="messageOpen"
            :title="trans('bookings.messages.custom.title')"
            :description="trans('bookings.messages.custom.description')"
        >
            <template #body>
                <div class="flex flex-col gap-4">
                    <UFormField
                        v-if="presetItems.length"
                        :label="trans('bookings.messages.custom.preset')"
                    >
                        <USelect
                            :items="presetItems"
                            class="w-full"
                            @update:model-value="
                                (preset) => (messageForm.body = String(preset))
                            "
                        />
                    </UFormField>
                    <UFormField
                        :label="trans('bookings.messages.custom.body')"
                        :error="messageForm.errors.body"
                        required
                    >
                        <UTextarea
                            v-model="messageForm.body"
                            :rows="4"
                            :maxlength="480"
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
                        @click="messageOpen = false"
                    />
                    <UButton
                        icon="i-lucide-send"
                        :label="trans('bookings.messages.custom.send')"
                        :loading="messageForm.processing"
                        :disabled="!messageForm.body.trim()"
                        @click="sendMessage"
                    />
                </div>
            </template>
        </UModal>

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
