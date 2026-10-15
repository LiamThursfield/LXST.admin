<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import * as z from 'zod';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Tracker/TaskDefinitionController';
import { todayIn, userTimezone } from '@/lib/timezone';
import { daysOfWeek, monthsOfYear } from '@/lib/tracker';
import type { SelectItem, TaskDefinitionData } from '@/types';

const { trans } = useTrans();

const props = withDefaults(
    defineProps<{
        task?: TaskDefinitionData;
        categories: SelectItem[];
        periods: SelectItem[];
        measures: SelectItem[];
        units: SelectItem[];
        comparators: SelectItem[];
        aggregations: SelectItem[];
        statuses: SelectItem[];
        readonly?: boolean;
        create?: boolean;
    }>(),
    {
        readonly: false,
        create: false,
    },
);

const schema = z.object({
    title: z
        .string()
        .min(1, trans('tracker.tasks.validation.title_required'))
        .max(120),
    description: z.string().max(1000).nullable(),
    category_id: z.number().nullable(),
    period: z
        .string()
        .min(1, trans('tracker.tasks.validation.period_required')),
    measure: z
        .string()
        .min(1, trans('tracker.tasks.validation.measure_required')),
    unit: z.string().min(1, trans('tracker.tasks.validation.unit_required')),
    comparator: z
        .string()
        .min(1, trans('tracker.tasks.validation.comparator_required')),
    aggregation: z
        .string()
        .min(1, trans('tracker.tasks.validation.aggregation_required')),
    target_min: z.number().nullable(),
    target_max: z.number().nullable(),
    stepped: z.boolean(),
    counts_during_holiday: z.boolean(),
    active_from: z
        .string()
        .min(1, trans('tracker.tasks.validation.active_from_required')),
    active_to: z.string().nullable(),
    status: z
        .string()
        .min(1, trans('tracker.tasks.validation.status_required')),
    days_of_week: z.array(z.number()),
    months_of_year: z.array(z.number()),
});

type Schema = z.output<typeof schema>;

// The user's local calendar day, matching the server's notion of today.
const today = todayIn(userTimezone(useAuth().value.user));

const state = reactive<Schema>({
    title: props.task?.title ?? '',
    description: props.task?.description ?? '',
    category_id: props.task?.category_id ?? null,
    period: props.task?.period ?? 'daily',
    measure: props.task?.measure ?? 'boolean',
    unit: props.task?.unit ?? 'boolean',
    comparator: props.task?.comparator ?? 'boolean',
    aggregation: props.task?.aggregation ?? 'any',
    target_min: props.task?.target_min ?? null,
    target_max: props.task?.target_max ?? null,
    stepped: props.task?.stepped ?? false,
    counts_during_holiday: props.task?.counts_during_holiday ?? false,
    active_from: props.task?.active_from ?? today,
    active_to: props.task?.active_to ?? null,
    status: props.task?.status ?? 'active',
    days_of_week: props.task?.schedule?.daysOfWeek ?? [],
    months_of_year: props.task?.schedule?.monthsOfYear ?? [],
});

const showMin = computed(() => ['min', 'between'].includes(state.comparator));
const showMax = computed(() => ['max', 'between'].includes(state.comparator));

// Initialise with every field so Inertia tracks them; onSubmit re-syncs from
// `state` (which the inputs are bound to) before submitting.
const form = useForm({
    title: state.title,
    description: state.description,
    category_id: state.category_id,
    period: state.period,
    measure: state.measure,
    unit: state.unit,
    comparator: state.comparator,
    aggregation: state.aggregation,
    target_min: state.target_min,
    target_max: state.target_max,
    stepped: state.stepped,
    counts_during_holiday: state.counts_during_holiday,
    active_from: state.active_from,
    active_to: state.active_to,
    status: state.status,
    schedule: {
        daysOfWeek: state.days_of_week.length ? state.days_of_week : null,
        monthsOfYear: state.months_of_year.length ? state.months_of_year : null,
    },
});

async function onSubmit() {
    if (props.readonly) return;

    form.title = state.title;
    form.description = state.description || null;
    form.category_id = state.category_id;
    form.period = state.period;
    form.measure = state.measure;
    form.unit = state.unit;
    form.comparator = state.comparator;
    form.aggregation = state.aggregation;
    form.target_min = showMin.value ? state.target_min : null;
    form.target_max = showMax.value ? state.target_max : null;
    form.stepped = state.stepped;
    form.counts_during_holiday = state.counts_during_holiday;
    form.active_from = state.active_from;
    form.active_to = state.active_to || null;
    form.status = state.status;
    form.schedule = {
        daysOfWeek: state.days_of_week.length ? state.days_of_week : null,
        monthsOfYear: state.months_of_year.length ? state.months_of_year : null,
    };

    if (props.create) {
        form.submit(store());
        return;
    }

    form.submit(update({ task: props.task!.id }));
}
</script>

<template>
    <UForm :schema="schema" :state="state" class="space-y-6" @submit="onSubmit">
        <UPageCard variant="subtle">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    class="md:col-span-2"
                    :label="trans('tracker.tasks.fields.title')"
                    name="title"
                    required
                >
                    <UInput
                        v-model="state.title"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    class="md:col-span-2"
                    :label="trans('tracker.tasks.fields.description')"
                    name="description"
                >
                    <UTextarea
                        v-model="state.description!"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="trans('tracker.tasks.fields.category')"
                    name="category_id"
                >
                    <USelect
                        v-model="state.category_id!"
                        :disabled="readonly"
                        :items="categories"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="trans('tracker.tasks.fields.status')"
                    name="status"
                    required
                >
                    <USelect
                        v-model="state.status"
                        :disabled="readonly"
                        :items="statuses"
                        class="w-full"
                    />
                </UFormField>
            </div>
        </UPageCard>

        <UPageCard
            variant="subtle"
            :title="trans('tracker.tasks.sections.measurement')"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    :label="trans('tracker.tasks.fields.measure')"
                    name="measure"
                    required
                >
                    <USelect
                        v-model="state.measure"
                        :disabled="readonly"
                        :items="measures"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="trans('tracker.tasks.fields.unit')"
                    name="unit"
                    required
                >
                    <USelect
                        v-model="state.unit"
                        :disabled="readonly"
                        :items="units"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="trans('tracker.tasks.fields.comparator')"
                    name="comparator"
                    required
                >
                    <USelect
                        v-model="state.comparator"
                        :disabled="readonly"
                        :items="comparators"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="trans('tracker.tasks.fields.aggregation')"
                    name="aggregation"
                    required
                >
                    <USelect
                        v-model="state.aggregation"
                        :disabled="readonly"
                        :items="aggregations"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    v-if="showMin"
                    :label="trans('tracker.tasks.fields.target_min')"
                    name="target_min"
                >
                    <UInput
                        v-model.number="state.target_min!"
                        :disabled="readonly"
                        class="w-full"
                        type="number"
                    />
                </UFormField>

                <UFormField
                    v-if="showMax"
                    :label="trans('tracker.tasks.fields.target_max')"
                    name="target_max"
                >
                    <UInput
                        v-model.number="state.target_max!"
                        :disabled="readonly"
                        class="w-full"
                        type="number"
                    />
                </UFormField>

                <UFormField
                    :label="trans('tracker.tasks.fields.stepped')"
                    name="stepped"
                >
                    <USwitch v-model="state.stepped" :disabled="readonly" />
                </UFormField>

                <UFormField
                    :label="trans('tracker.tasks.fields.counts_during_holiday')"
                    name="counts_during_holiday"
                >
                    <USwitch
                        v-model="state.counts_during_holiday"
                        :disabled="readonly"
                    />
                </UFormField>
            </div>
        </UPageCard>

        <UPageCard
            variant="subtle"
            :title="trans('tracker.tasks.sections.schedule')"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    :label="trans('tracker.tasks.fields.period')"
                    name="period"
                    required
                >
                    <USelect
                        v-model="state.period"
                        :disabled="readonly"
                        :items="periods"
                        class="w-full"
                    />
                </UFormField>

                <div class="hidden md:block" />

                <UFormField
                    :label="trans('tracker.tasks.fields.active_from')"
                    name="active_from"
                    required
                >
                    <UInput
                        v-model="state.active_from"
                        :disabled="readonly"
                        class="w-full"
                        type="date"
                    />
                </UFormField>

                <UFormField
                    :label="trans('tracker.tasks.fields.active_to')"
                    name="active_to"
                >
                    <UInput
                        v-model="state.active_to!"
                        :disabled="readonly"
                        class="w-full"
                        type="date"
                    />
                </UFormField>

                <UFormField
                    :label="trans('tracker.tasks.fields.days_of_week')"
                    name="days_of_week"
                >
                    <USelect
                        v-model="state.days_of_week"
                        :disabled="readonly"
                        :items="daysOfWeek()"
                        class="w-full"
                        multiple
                    />
                </UFormField>

                <UFormField
                    :label="trans('tracker.tasks.fields.months_of_year')"
                    name="months_of_year"
                >
                    <USelect
                        v-model="state.months_of_year"
                        :disabled="readonly"
                        :items="monthsOfYear()"
                        class="w-full"
                        multiple
                    />
                </UFormField>
            </div>
        </UPageCard>

        <div v-if="!readonly" class="flex justify-end gap-2">
            <UButton
                :loading="form.processing"
                color="primary"
                :label="
                    create
                        ? trans('tracker.tasks.create.submit')
                        : trans('tracker.save_changes')
                "
                type="submit"
            />
        </div>
    </UForm>
</template>
