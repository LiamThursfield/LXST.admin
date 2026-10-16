<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { store } from '@/actions/App/Http/Controllers/Tenant/Admin/FileManager/FileController';

const props = defineProps<{
    /** The folder to upload into, or null for the top level. */
    folder: { id: number; name: string } | null;
    rules: { extensions: string[]; maxSizeKb: number; maxFiles: number };
}>();

const open = defineModel<boolean>('open', { required: true });

const { trans, transChoice } = useTrans();

const form = useForm({
    folder_id: null as number | null,
    files: [] as File[],
});

const accept = computed(() =>
    props.rules.extensions.map((extension) => `.${extension}`).join(','),
);

/** Server validation errors for individual files, e.g. `files.1`, with the file's name. */
const fileErrors = computed(() =>
    Object.entries(form.errors as Record<string, string>)
        .filter(([key]) => key.startsWith('files.'))
        .map(([, message]) => message),
);

function submit() {
    form.folder_id = props.folder?.id ?? null;

    form.submit(store(), {
        forceFormData: true,
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
        :title="trans('file_manager.files.upload_title')"
        :description="
            trans('file_manager.files.upload_description', {
                folder: folder?.name ?? trans('file_manager.top_level'),
            })
        "
    >
        <template #body>
            <form class="space-y-4" @submit.prevent="submit">
                <UFileUpload
                    v-model="form.files"
                    multiple
                    layout="list"
                    class="min-h-40 w-full"
                    :accept="accept"
                    :label="trans('file_manager.files.upload_title')"
                    :description="
                        trans('file_manager.files.upload_hint', {
                            count: rules.maxFiles,
                            size: Math.round(rules.maxSizeKb / 1024),
                            types: rules.extensions.join(', '),
                        })
                    "
                />

                <UProgress
                    v-if="form.progress"
                    :model-value="form.progress.percentage"
                />

                <div
                    v-if="form.errors.files || fileErrors.length"
                    class="text-error space-y-1 text-sm"
                >
                    <p v-if="form.errors.files">{{ form.errors.files }}</p>
                    <p v-for="message in fileErrors" :key="message">
                        {{ message }}
                    </p>
                </div>

                <div class="flex justify-end gap-2">
                    <UButton
                        color="neutral"
                        variant="subtle"
                        :label="trans('common.actions.cancel')"
                        @click="open = false"
                    />
                    <UButton
                        type="submit"
                        :disabled="!form.files.length"
                        :loading="form.processing"
                        :label="
                            transChoice(
                                'file_manager.files.upload_submit',
                                form.files.length,
                            )
                        "
                    />
                </div>
            </form>
        </template>
    </UModal>
</template>
