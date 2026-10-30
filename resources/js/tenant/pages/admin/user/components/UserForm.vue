<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import * as z from 'zod';
import {
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/UserController';

const { trans } = useTrans();

const props = withDefaults(
    defineProps<{
        user?: {
            id: number;
            first_name: string;
            last_name: string;
            email: string;
            roles?: string[];
        };
        roles: Array<{ label: string; value: string }>;
        readonly?: boolean;
        create?: boolean;
    }>(),
    {
        readonly: false,
        create: false,
    },
);

const schema = z.object({
    first_name: z
        .string()
        .min(1, trans('users.validation.first_name_required'))
        .max(255),
    last_name: z
        .string()
        .min(1, trans('users.validation.last_name_required'))
        .max(255),
    email: z.email(trans('users.validation.email_invalid')).max(255),
    roles: z.array(z.string()).nullable(),
});

type Schema = z.output<typeof schema>;

const state = reactive<Partial<Schema>>({
    first_name: props.user?.first_name ?? '',
    last_name: props.user?.last_name ?? '',
    email: props.user?.email ?? '',
    roles: props.user?.roles ?? [],
});

const form = useForm<Partial<Schema>>({
    first_name: '',
    last_name: '',
    email: '',
    roles: [],
});

// The fields are the UForm's state, only copied into the Inertia form on submit
const { isDirty } = useIsDirty(() => state);

const formRef = useTemplateRef<{ submit: () => Promise<void> }>('formRef');

const userName = computed(() =>
    props.user ? `${props.user.first_name} ${props.user.last_name}` : '',
);

const title = computed(() => {
    if (props.create || !props.user) {
        return trans('users.create.title');
    }

    return trans(props.readonly ? 'users.show.title' : 'users.edit.title', {
        name: userName.value,
    });
});

const breadcrumbs = computed(() => [
    { label: trans('users.index.title'), to: index.url() },
    { label: userName.value || trans('users.create.title') },
]);

async function onSubmit() {
    if (props.readonly) return;

    form.first_name = state.first_name;
    form.last_name = state.last_name;
    form.email = state.email;
    form.roles = state.roles || [];

    // If we are creating a user, call the store endpoint
    if (props.create) {
        form.submit(store());
        return;
    }

    // Otherwise, update the correct user
    form.submit(update({ user: props.user!.id }));
}
</script>

<template>
    <EditorLayout
        :title="title"
        :breadcrumbs="breadcrumbs"
        :is-dirty="isDirty"
        :processing="form.processing"
        :readonly="readonly"
        :submit-label="create ? trans('users.create.submit') : undefined"
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
                        :label="trans('users.fields.first_name')"
                        name="first_name"
                        required
                    >
                        <UInput
                            v-model="state.first_name"
                            :disabled="readonly"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        :label="trans('users.fields.last_name')"
                        name="last_name"
                        required
                    >
                        <UInput
                            v-model="state.last_name"
                            :disabled="readonly"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        class="md:col-span-2"
                        :label="trans('users.fields.email')"
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

                    <UFormField
                        class="md:col-span-2"
                        :label="trans('users.fields.roles')"
                        name="roles"
                    >
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

            <p v-if="create" class="text-muted text-sm">
                {{ trans('users.create.password_email_notice') }}
            </p>

            <SubmitOnEnter />
        </UForm>
    </EditorLayout>
</template>
