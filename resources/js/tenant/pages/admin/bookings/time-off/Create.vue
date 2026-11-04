<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    index,
    store,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/TimeOffController';
import type { SelectItem } from '@/types';

const props = defineProps<{
    resources: SelectItem[];
    types: SelectItem[];
    canManage: boolean;
}>();

const { trans } = useTrans();

const form = useForm({
    resource_id: (props.resources[0]?.value ?? null) as number | null,
    type: props.types[0].value as string,
    starts_on: '',
    ends_on: null as string | null,
    reason: '',
});

const breadcrumbs = computed(() => [
    { label: trans('bookings.time_off.index.title'), to: index.url() },
    { label: trans('bookings.time_off.create.title') },
]);
</script>

<template>
    <EditorLayout
        :title="trans('bookings.time_off.create.title')"
        :breadcrumbs="breadcrumbs"
        :is-dirty="form.isDirty"
        :processing="form.processing"
        :submit-label="
            trans(
                canManage
                    ? 'bookings.time_off.create.record_submit'
                    : 'bookings.time_off.create.request_submit',
            )
        "
        narrow
        @submit="form.submit(store())"
    >
        <UAlert
            v-if="!resources.length"
            color="warning"
            variant="subtle"
            :title="trans('bookings.time_off.create.none_linked')"
        />

        <UPageCard
            variant="subtle"
            :description="
                trans(
                    canManage
                        ? 'bookings.time_off.create.record_help'
                        : 'bookings.time_off.create.request_help',
                )
            "
        >
            <UFormField
                :label="trans('bookings.time_off.fields.resource')"
                name="resource_id"
                :error="form.errors.resource_id"
                required
            >
                <USelect
                    v-model="form.resource_id"
                    :items="resources"
                    class="w-full"
                />
            </UFormField>

            <UFormField
                :label="trans('bookings.time_off.fields.type')"
                name="type"
                :error="form.errors.type"
                required
            >
                <USelect v-model="form.type" :items="types" class="w-full" />
            </UFormField>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    :label="trans('bookings.time_off.fields.starts_on')"
                    name="starts_on"
                    :error="form.errors.starts_on"
                    required
                >
                    <UInput
                        v-model="form.starts_on"
                        type="date"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="trans('bookings.time_off.fields.ends_on')"
                    :help="trans('bookings.time_off.fields.ends_on_help')"
                    name="ends_on"
                    :error="form.errors.ends_on"
                >
                    <UInput
                        v-model.nullable="form.ends_on"
                        type="date"
                        class="w-full"
                    />
                </UFormField>
            </div>

            <UFormField
                :label="trans('bookings.time_off.fields.reason')"
                name="reason"
                :error="form.errors.reason"
            >
                <UTextarea v-model="form.reason" :rows="3" class="w-full" />
            </UFormField>
        </UPageCard>
    </EditorLayout>
</template>
