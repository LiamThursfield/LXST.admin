<script setup lang="ts">
import type { OpeningPeriodData } from '@/types';

/** One row of opening hours: when it opens and closes, and an optional label. */
const props = withDefaults(
    defineProps<{
        /** Server validation errors, keyed by the full field name. */
        errors?: Record<string, string | undefined>;
        /** The field path the period is submitted under, e.g. `opening_hours.0`. */
        path: string;
        readonly?: boolean;
    }>(),
    {
        errors: () => ({}),
        readonly: false,
    },
);

const period = defineModel<OpeningPeriodData>({ required: true });

const emit = defineEmits<{ remove: [] }>();

const { trans } = useTrans();

function error(field: string): string | undefined {
    return props.errors[`${props.path}.${field}`];
}
</script>

<template>
    <div class="flex flex-wrap items-start gap-2">
        <UFormField
            :label="trans('crm.opening_hours.fields.opens_at')"
            :name="`${path}.opens_at`"
            :error="error('opens_at')"
            class="w-32"
        >
            <UInput
                v-model="period.opens_at"
                :disabled="readonly"
                class="w-full"
                type="time"
            />
        </UFormField>
        <UFormField
            :label="trans('crm.opening_hours.fields.closes_at')"
            :name="`${path}.closes_at`"
            :error="error('closes_at')"
            class="w-32"
        >
            <UInput
                v-model="period.closes_at"
                :disabled="readonly"
                class="w-full"
                type="time"
            />
        </UFormField>
        <UFormField
            :label="trans('crm.opening_hours.fields.label')"
            :name="`${path}.label`"
            :error="error('label')"
            class="min-w-40 flex-1"
        >
            <UInput
                v-model.nullable="period.label"
                :disabled="readonly"
                :placeholder="
                    trans('crm.opening_hours.fields.label_placeholder')
                "
                class="w-full"
            />
        </UFormField>
        <UButton
            v-if="!readonly"
            class="mt-6"
            color="neutral"
            variant="ghost"
            icon="i-lucide-x"
            :aria-label="trans('crm.opening_hours.remove_period')"
            @click="emit('remove')"
        />
    </div>
</template>
