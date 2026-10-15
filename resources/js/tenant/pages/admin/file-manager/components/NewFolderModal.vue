<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { store } from '@/actions/App/Http/Controllers/Tenant/Admin/FileManager/FolderController';

const props = defineProps<{
    /** The folder to create the new folder in, or null for the top level. */
    parent: { id: number; name: string } | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const { trans } = useTrans();

const form = useForm({
    parent_id: null as number | null,
    name: '',
});

function submit() {
    form.parent_id = props.parent?.id ?? null;

    form.submit(store(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            open.value = false;
        },
    });
}
</script>

<template>
    <UModal
        v-model:open="open"
        :title="trans('file_manager.folders.create_title')"
        :description="
            trans('file_manager.folders.create_description', {
                folder: parent?.name ?? trans('file_manager.top_level'),
            })
        "
    >
        <template #body>
            <form class="space-y-4" @submit.prevent="submit">
                <UFormField
                    :label="trans('file_manager.folders.name')"
                    name="name"
                    :error="form.errors.name || form.errors.parent_id"
                    required
                >
                    <UInput v-model="form.name" class="w-full" autofocus />
                </UFormField>

                <div class="flex justify-end gap-2">
                    <UButton
                        color="neutral"
                        variant="subtle"
                        :label="trans('common.actions.cancel')"
                        @click="open = false"
                    />
                    <UButton
                        type="submit"
                        :loading="form.processing"
                        :label="trans('file_manager.folders.submit')"
                    />
                </div>
            </form>
        </template>
    </UModal>
</template>
