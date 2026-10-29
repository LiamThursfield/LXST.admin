<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import * as z from 'zod';
import {
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Tracker/HolidayController';
import { todayIn, userTimezone } from '@/lib/timezone';

const { trans } = useTrans();

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

// The user's local calendar day, matching the server's notion of today.
const today = todayIn(userTimezone(useAuth().value.user));

const schema = z.object({
    label: z.string().max(120).nullable(),
    starts_on: z
        .string()
        .min(1, trans('tracker.holidays.validation.starts_on_required')),
    ends_on: z
        .string()
        .min(1, trans('tracker.holidays.validation.ends_on_required')),
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

// The fields are the UForm's state, only copied into the Inertia form on submit
const { isDirty } = useIsDirty(() => state);

const formRef = useTemplateRef<{ submit: () => Promise<void> }>('formRef');

const title = computed(() => {
    if (props.create || !props.holiday) {
        return trans('tracker.holidays.create.title');
    }

    return trans(
        props.readonly
            ? 'tracker.holidays.show.title'
            : 'tracker.holidays.edit.title',
    );
});

const breadcrumbs = computed(() => [
    { label: trans('tracker.holidays.index.title'), to: index.url() },
    {
        label: props.holiday
            ? props.holiday.label ||
              `${props.holiday.starts_on} – ${props.holiday.ends_on}`
            : trans('tracker.holidays.create.title'),
    },
]);

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
    <EditorLayout
        :title="title"
        :breadcrumbs="breadcrumbs"
        :is-dirty="isDirty"
        :processing="form.processing"
        :readonly="readonly"
        :submit-label="
            create ? trans('tracker.holidays.create.submit') : undefined
        "
        narrow
        own-form
        @submit="formRef?.submit()"
    >
        <UForm
            ref="formRef"
            :schema="schema"
            :state="state"
            @submit="onSubmit"
            class="flex flex-col gap-6"
        >
            <UPageCard variant="subtle">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <UFormField
                        class="md:col-span-2"
                        :label="trans('tracker.holidays.fields.label')"
                        name="label"
                    >
                        <UInput
                            v-model="state.label!"
                            :disabled="readonly"
                            class="w-full"
                            :placeholder="
                                trans(
                                    'tracker.holidays.fields.label_placeholder',
                                )
                            "
                        />
                    </UFormField>

                    <UFormField
                        :label="trans('tracker.holidays.fields.starts_on')"
                        name="starts_on"
                        required
                    >
                        <UInput
                            v-model="state.starts_on"
                            :disabled="readonly"
                            class="w-full"
                            type="date"
                        />
                    </UFormField>

                    <UFormField
                        :label="trans('tracker.holidays.fields.ends_on')"
                        name="ends_on"
                        required
                    >
                        <UInput
                            v-model="state.ends_on"
                            :disabled="readonly"
                            class="w-full"
                            type="date"
                        />
                    </UFormField>
                </div>
            </UPageCard>

            <SubmitOnEnter />
        </UForm>
    </EditorLayout>
</template>
