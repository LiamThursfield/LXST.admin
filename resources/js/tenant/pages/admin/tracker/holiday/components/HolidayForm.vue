<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import * as z from 'zod';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Tracker/HolidayController';

const props = withDefaults(
    defineProps<{
        holiday?: {
            id: number;
            label?: string | null;
            starts_on: string;
            ends_on: string;
        };
        readonly?: boolean;
        create?: boolean;
    }>(),
    {
        readonly: false,
        create: false,
    },
);

const today = new Date().toISOString().slice(0, 10);

const schema = z.object({
    label: z.string().max(120).nullable(),
    starts_on: z.string().min(1, 'Start date is required'),
    ends_on: z.string().min(1, 'End date is required'),
});

type Schema = z.output<typeof schema>;

const state = reactive<Schema>({
    label: props.holiday?.label ?? '',
    starts_on: props.holiday?.starts_on ?? today,
    ends_on: props.holiday?.ends_on ?? today,
});

const form = useForm({
    label: state.label,
    starts_on: state.starts_on,
    ends_on: state.ends_on,
});

async function onSubmit() {
    if (props.readonly) return;

    form.label = state.label || null;
    form.starts_on = state.starts_on;
    form.ends_on = state.ends_on;

    if (props.create) {
        form.submit(store());
        return;
    }

    form.submit(update({ holiday: props.holiday!.id }));
}
</script>

<template>
    <UForm :schema="schema" :state="state" class="space-y-6" @submit="onSubmit">
        <UPageCard variant="subtle">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField class="md:col-span-2" label="Label" name="label">
                    <UInput
                        v-model="state.label!"
                        :disabled="readonly"
                        class="w-full"
                        placeholder="e.g. Italy trip"
                    />
                </UFormField>

                <UFormField label="Start date" name="starts_on" required>
                    <UInput
                        v-model="state.starts_on"
                        :disabled="readonly"
                        class="w-full"
                        type="date"
                    />
                </UFormField>

                <UFormField label="End date" name="ends_on" required>
                    <UInput
                        v-model="state.ends_on"
                        :disabled="readonly"
                        class="w-full"
                        type="date"
                    />
                </UFormField>
            </div>
        </UPageCard>

        <div v-if="!readonly" class="flex justify-end gap-2">
            <UButton
                :loading="form.processing"
                color="primary"
                :label="create ? 'Create Holiday' : 'Save Changes'"
                type="submit"
            />
        </div>
    </UForm>
</template>
