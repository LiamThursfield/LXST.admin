<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/Central/Admin/UserController';
import type { CentralUserData } from '@/types';

const props = defineProps<{
    /** The user being edited; left out when inviting one */
    user?: CentralUserData;
}>();

const { trans } = useTrans();

const form = useForm({
    first_name: props.user?.first_name ?? '',
    last_name: props.user?.last_name ?? '',
    email: props.user?.email ?? '',
});

const userName = computed(() =>
    props.user ? `${props.user.first_name} ${props.user.last_name}` : '',
);

const title = computed(() =>
    props.user
        ? trans('users.edit.title', { name: userName.value })
        : trans('users.create.title'),
);

const breadcrumbs = computed(() => [
    { label: trans('users.index.title'), to: index.url() },
    { label: userName.value || trans('users.create.title') },
]);

function onSubmit() {
    form.submit(props.user ? update({ user: props.user.id }) : store());
}
</script>

<template>
    <EditorLayout
        :title="title"
        :breadcrumbs="breadcrumbs"
        :is-dirty="form.isDirty"
        :processing="form.processing"
        :submit-label="user ? undefined : trans('users.create.submit')"
        narrow
        @submit="onSubmit"
    >
        <UPageCard
            variant="subtle"
            :description="
                user ? undefined : trans('users.central.invite_notice')
            "
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    :label="trans('users.fields.first_name')"
                    :error="form.errors.first_name"
                    required
                >
                    <UInput v-model="form.first_name" class="w-full" />
                </UFormField>

                <UFormField
                    :label="trans('users.fields.last_name')"
                    :error="form.errors.last_name"
                    required
                >
                    <UInput v-model="form.last_name" class="w-full" />
                </UFormField>

                <UFormField
                    :label="trans('users.fields.email')"
                    :error="form.errors.email"
                    class="md:col-span-2"
                    required
                >
                    <UInput v-model="form.email" type="email" class="w-full" />
                </UFormField>
            </div>
        </UPageCard>
    </EditorLayout>
</template>
