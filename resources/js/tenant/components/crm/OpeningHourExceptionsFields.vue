<script setup lang="ts">
import type { OpeningHourExceptionData } from '@/types';
import OpeningPeriodFields from './OpeningPeriodFields.vue';

/** Dates that replace a location's weekly hours: closed, or with hours of their own. */
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

const exceptions = defineModel<OpeningHourExceptionData[]>({ required: true });

const { trans } = useTrans();

function addException() {
    exceptions.value.push({
        name: '',
        starts_on: '',
        ends_on: null,
        periods: [],
    });
}

function addPeriod(exception: OpeningHourExceptionData) {
    exception.periods.push({
        opens_at: '09:00',
        closes_at: '17:00',
        label: null,
    });
}
</script>

<template>
    <UPageCard
        variant="subtle"
        :title="trans('crm.opening_hours.exceptions_title')"
        :description="
            readonly
                ? undefined
                : trans('crm.opening_hours.exceptions_description')
        "
    >
        <p v-if="errors.opening_hour_exceptions" class="text-error text-sm">
            {{ errors.opening_hour_exceptions }}
        </p>

        <p v-if="!exceptions.length" class="text-muted text-sm">
            {{ trans('crm.opening_hours.no_exceptions') }}
        </p>

        <div
            v-for="(exception, index) in exceptions"
            :key="index"
            class="border-default flex flex-col gap-3 rounded-md border p-3"
        >
            <div class="flex flex-wrap items-start gap-2">
                <UFormField
                    :label="trans('crm.opening_hours.fields.exception_name')"
                    :name="`opening_hour_exceptions.${index}.name`"
                    :error="errors[`opening_hour_exceptions.${index}.name`]"
                    class="min-w-48 flex-1"
                    required
                >
                    <UInput
                        v-model="exception.name"
                        :disabled="readonly"
                        :placeholder="
                            trans(
                                'crm.opening_hours.fields.exception_name_placeholder',
                            )
                        "
                        class="w-full"
                    />
                </UFormField>
                <UFormField
                    :label="trans('crm.opening_hours.fields.starts_on')"
                    :name="`opening_hour_exceptions.${index}.starts_on`"
                    :error="
                        errors[`opening_hour_exceptions.${index}.starts_on`]
                    "
                    class="w-40"
                    required
                >
                    <UInput
                        v-model="exception.starts_on"
                        :disabled="readonly"
                        class="w-full"
                        type="date"
                    />
                </UFormField>
                <UFormField
                    :label="trans('crm.opening_hours.fields.ends_on')"
                    :help="
                        readonly
                            ? undefined
                            : trans('crm.opening_hours.fields.ends_on_help')
                    "
                    :name="`opening_hour_exceptions.${index}.ends_on`"
                    :error="errors[`opening_hour_exceptions.${index}.ends_on`]"
                    class="w-40"
                >
                    <UInput
                        v-model.nullable="exception.ends_on"
                        :disabled="readonly"
                        class="w-full"
                        type="date"
                    />
                </UFormField>
                <UButton
                    v-if="!readonly"
                    class="mt-6"
                    color="neutral"
                    variant="ghost"
                    icon="i-lucide-trash"
                    :aria-label="trans('crm.opening_hours.remove_exception')"
                    @click="exceptions.splice(index, 1)"
                />
            </div>

            <p
                v-if="errors[`opening_hour_exceptions.${index}.periods`]"
                class="text-error text-sm"
            >
                {{ errors[`opening_hour_exceptions.${index}.periods`] }}
            </p>

            <p v-if="!exception.periods.length" class="text-muted text-sm">
                {{ trans('crm.opening_hours.closed') }}
            </p>

            <OpeningPeriodFields
                v-for="(_, periodIndex) in exception.periods"
                :key="periodIndex"
                v-model="exception.periods[periodIndex]"
                :errors="errors"
                :path="`opening_hour_exceptions.${index}.periods.${periodIndex}`"
                :readonly="readonly"
                @remove="exception.periods.splice(periodIndex, 1)"
            />

            <UButton
                v-if="!readonly"
                class="w-fit"
                color="neutral"
                variant="subtle"
                size="xs"
                icon="i-lucide-plus"
                :label="trans('crm.opening_hours.add_period')"
                @click="addPeriod(exception)"
            />
        </div>

        <UButton
            v-if="!readonly"
            class="w-fit"
            color="neutral"
            variant="subtle"
            icon="i-lucide-plus"
            :label="trans('crm.opening_hours.add_exception')"
            @click="addException"
        />
    </UPageCard>
</template>
