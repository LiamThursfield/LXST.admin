<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import * as z from 'zod';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Tracker/TaskDefinitionController';
import { daysOfWeek, monthsOfYear } from '@/lib/tracker';
import type { SelectItem, TaskDefinitionData } from '@/types';

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
    title: z.string().min(1, 'Title is required').max(120),
    description: z.string().max(1000).nullable(),
    category_id: z.number().nullable(),
    period: z.string().min(1, 'Period is required'),
    measure: z.string().min(1, 'Measure is required'),
    unit: z.string().min(1, 'Unit is required'),
    comparator: z.string().min(1, 'Comparator is required'),
    aggregation: z.string().min(1, 'Aggregation is required'),
    target_min: z.number().nullable(),
    target_max: z.number().nullable(),
    stepped: z.boolean(),
    counts_during_holiday: z.boolean(),
    active_from: z.string().min(1, 'Start date is required'),
    active_to: z.string().nullable(),
    status: z.string().min(1, 'Status is required'),
    days_of_week: z.array(z.number()),
    months_of_year: z.array(z.number()),
});

type Schema = z.output<typeof schema>;

const today = new Date().toISOString().slice(0, 10);

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

    form.submit(update({ task: props.task!.id.toString() }));
}
</script>

<template>
    <UForm :schema="schema" :state="state" class="space-y-6" @submit="onSubmit">
        <UPageCard variant="subtle">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    class="md:col-span-2"
                    label="Title"
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
                    label="Description"
                    name="description"
                >
                    <UTextarea
                        v-model="state.description!"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>

                <UFormField label="Category" name="category_id">
                    <USelect
                        v-model="state.category_id!"
                        :disabled="readonly"
                        :items="categories"
                        class="w-full"
                    />
                </UFormField>

                <UFormField label="Status" name="status" required>
                    <USelect
                        v-model="state.status"
                        :disabled="readonly"
                        :items="statuses"
                        class="w-full"
                    />
                </UFormField>
            </div>
        </UPageCard>

        <UPageCard variant="subtle" title="Measurement">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField label="Measure" name="measure" required>
                    <USelect
                        v-model="state.measure"
                        :disabled="readonly"
                        :items="measures"
                        class="w-full"
                    />
                </UFormField>

                <UFormField label="Unit" name="unit" required>
                    <USelect
                        v-model="state.unit"
                        :disabled="readonly"
                        :items="units"
                        class="w-full"
                    />
                </UFormField>

                <UFormField label="Comparator" name="comparator" required>
                    <USelect
                        v-model="state.comparator"
                        :disabled="readonly"
                        :items="comparators"
                        class="w-full"
                    />
                </UFormField>

                <UFormField label="Aggregation" name="aggregation" required>
                    <USelect
                        v-model="state.aggregation"
                        :disabled="readonly"
                        :items="aggregations"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    v-if="showMin"
                    label="Target (min)"
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
                    label="Target (max)"
                    name="target_max"
                >
                    <UInput
                        v-model.number="state.target_max!"
                        :disabled="readonly"
                        class="w-full"
                        type="number"
                    />
                </UFormField>

                <UFormField label="Stepped entries" name="stepped">
                    <USwitch v-model="state.stepped" :disabled="readonly" />
                </UFormField>

                <UFormField
                    label="Counts during holiday"
                    name="counts_during_holiday"
                >
                    <USwitch
                        v-model="state.counts_during_holiday"
                        :disabled="readonly"
                    />
                </UFormField>
            </div>
        </UPageCard>

        <UPageCard variant="subtle" title="Schedule">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField label="Period" name="period" required>
                    <USelect
                        v-model="state.period"
                        :disabled="readonly"
                        :items="periods"
                        class="w-full"
                    />
                </UFormField>

                <div class="hidden md:block" />

                <UFormField label="Start date" name="active_from" required>
                    <UInput
                        v-model="state.active_from"
                        :disabled="readonly"
                        class="w-full"
                        type="date"
                    />
                </UFormField>

                <UFormField label="End date" name="active_to">
                    <UInput
                        v-model="state.active_to!"
                        :disabled="readonly"
                        class="w-full"
                        type="date"
                    />
                </UFormField>

                <UFormField label="Days of week" name="days_of_week">
                    <USelect
                        v-model="state.days_of_week"
                        :disabled="readonly"
                        :items="daysOfWeek"
                        class="w-full"
                        multiple
                    />
                </UFormField>

                <UFormField label="Months of year" name="months_of_year">
                    <USelect
                        v-model="state.months_of_year"
                        :disabled="readonly"
                        :items="monthsOfYear"
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
                :label="create ? 'Create Task' : 'Save Changes'"
                type="submit"
            />
        </div>
    </UForm>
</template>
