<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import * as z from 'zod';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Tracker/CategoryController';

const { trans } = useTrans();

const props = withDefaults(
    defineProps<{
        category?: {
            id: number;
            name: string;
            colour?: string | null;
            icon?: string | null;
            is_public: boolean;
        };
        canManagePublic?: boolean;
        readonly?: boolean;
        create?: boolean;
    }>(),
    {
        canManagePublic: false,
        readonly: false,
        create: false,
    },
);

const schema = z.object({
    name: z
        .string()
        .min(1, trans('tracker.categories.validation.name_required'))
        .max(80),
    colour: z.string().max(30).nullable(),
    icon: z.string().max(60).nullable(),
    is_public: z.boolean(),
});

type Schema = z.output<typeof schema>;

const state = reactive<Partial<Schema>>({
    name: props.category?.name ?? '',
    colour: props.category?.colour ?? '',
    icon: props.category?.icon ?? '',
    is_public: props.category?.is_public ?? false,
});

const form = useForm<Partial<Schema>>({
    name: '',
    colour: '',
    icon: '',
    is_public: false,
});

async function onSubmit() {
    if (props.readonly) return;

    form.name = state.name;
    form.colour = state.colour || null;
    form.icon = state.icon || null;
    form.is_public = state.is_public ?? false;

    if (props.create) {
        form.submit(store());
        return;
    }

    form.submit(update({ category: props.category!.id.toString() }));
}
</script>

<template>
    <UForm :schema="schema" :state="state" class="space-y-6" @submit="onSubmit">
        <UPageCard variant="subtle">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    class="md:col-span-2"
                    :label="trans('tracker.categories.fields.name')"
                    name="name"
                    required
                >
                    <UInput
                        v-model="state.name"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="trans('tracker.categories.fields.colour')"
                    name="colour"
                >
                    <UInput
                        v-model="state.colour!"
                        :disabled="readonly"
                        class="w-full"
                        placeholder="#4f46e5"
                    />
                </UFormField>

                <UFormField
                    :label="trans('tracker.categories.fields.icon')"
                    name="icon"
                >
                    <UInput
                        v-model="state.icon!"
                        :disabled="readonly"
                        class="w-full"
                        placeholder="i-lucide-tag"
                    />
                </UFormField>

                <UFormField
                    v-if="canManagePublic"
                    class="md:col-span-2"
                    :label="trans('tracker.categories.fields.is_public')"
                    name="is_public"
                    :description="
                        trans('tracker.categories.fields.is_public_description')
                    "
                >
                    <USwitch v-model="state.is_public!" :disabled="readonly" />
                </UFormField>
            </div>
        </UPageCard>

        <div v-if="!readonly" class="flex justify-end gap-2">
            <UButton
                :loading="form.processing"
                color="primary"
                :label="
                    create
                        ? trans('tracker.categories.create.submit')
                        : trans('tracker.save_changes')
                "
                type="submit"
            />
        </div>
    </UForm>
</template>
