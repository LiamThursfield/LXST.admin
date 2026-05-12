<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import * as z from 'zod';
import { update } from '@/actions/App/Http/Controllers/Tenant/Admin/UserController';

const props = defineProps<{
    user: {
        id: number;
        first_name: string;
        last_name: string;
        email: string;
        roles?: string[];
    };
    roles: Array<{ label: string; value: string }>;
    readonly: boolean;
}>();

const schema = z.object({
    first_name: z.string().min(1, 'First name is required').max(255),
    last_name: z.string().min(1, 'Last name is required').max(255),
    email: z.email('Invalid email address').max(255),
    roles: z.array(z.string()).nullable(),
});

type Schema = z.output<typeof schema>;

const state = reactive<Partial<Schema>>({
    first_name: props.user.first_name,
    last_name: props.user.last_name,
    email: props.user.email,
    roles: props.user.roles || [],
});

const form = useForm<Partial<Schema>>({
    first_name: '',
    last_name: '',
    email: '',
    roles: [],
});

async function onSubmit() {
    if (props.readonly) return;

    form.first_name = state.first_name;
    form.last_name = state.last_name;
    form.email = state.email;
    form.roles = state.roles || [];

    form.submit(update({ user: props.user.id.toString() }));
}
</script>

<template>
    <UForm :schema="schema" :state="state" class="space-y-6" @submit="onSubmit">
        <UPageCard variant="subtle">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField label="First Name" name="first_name" required>
                    <UInput
                        v-model="state.first_name"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>

                <UFormField label="Last Name" name="last_name" required>
                    <UInput
                        v-model="state.last_name"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    class="md:col-span-2"
                    label="Email"
                    name="email"
                    required
                >
                    <UInput
                        v-model="state.email"
                        :disabled="readonly"
                        class="w-full"
                        type="email"
                    />
                </UFormField>

                <UFormField class="md:col-span-2" label="Roles" name="roles">
                    <USelect
                        v-model="state.roles!"
                        :disabled="readonly"
                        :items="roles"
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
                label="Save Changes"
                type="submit"
            />
        </div>
    </UForm>
</template>
