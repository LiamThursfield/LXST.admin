<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { update } from '@/actions/App/Http/Controllers/Tenant/Admin/FileManager/FileController';
import { formatBytes } from '@/lib/files';
import { formatDateTime, userTimezone } from '@/lib/timezone';
import type { FileManagerFile } from '@/types';

const props = defineProps<{
    file: FileManagerFile;
    /** The folder path the file is in, for display. */
    location: string;
    canEdit: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const { trans } = useTrans();
const toast = useToast();
const timezone = userTimezone(useAuth().value.user);

const form = useForm({
    alt_text: props.file.alt_text ?? '',
});

// Show the selected file's own alt text when a different file is opened
watch(
    () => props.file.id,
    () => {
        form.defaults({ alt_text: props.file.alt_text ?? '' });
        form.reset();
        form.clearErrors();
    },
);

async function copyUrl() {
    await navigator.clipboard.writeText(props.file.url);
    toast.add({
        title: trans('file_manager.files.copied'),
        icon: 'i-lucide-copy',
    });
}

function save() {
    form.submit(update({ file: props.file.id }), { preserveScroll: true });
}
</script>

<template>
    <USlideover
        v-model:open="open"
        :title="file.name"
        :description="trans('file_manager.files.details')"
    >
        <template #body>
            <div class="flex flex-col gap-6">
                <img
                    v-if="file.is_image"
                    :src="file.url"
                    :alt="file.alt_text ?? ''"
                    class="bg-elevated max-h-72 w-full rounded-md object-contain"
                />

                <dl class="grid grid-cols-3 gap-2 text-sm">
                    <dt class="text-muted">
                        {{ trans('file_manager.files.type') }}
                    </dt>
                    <dd class="col-span-2 break-all">
                        {{ file.mime_type ?? '-' }}
                    </dd>
                    <dt class="text-muted">
                        {{ trans('file_manager.files.size') }}
                    </dt>
                    <dd class="col-span-2">{{ formatBytes(file.size) }}</dd>
                    <dt class="text-muted">
                        {{ trans('file_manager.files.location') }}
                    </dt>
                    <dd class="col-span-2 break-all">{{ location }}</dd>
                    <template v-if="file.created_at">
                        <dt class="text-muted">
                            {{ trans('file_manager.files.added') }}
                        </dt>
                        <dd class="col-span-2">
                            {{ formatDateTime(file.created_at, timezone) }}
                        </dd>
                    </template>
                </dl>

                <div class="flex flex-col gap-2">
                    <UInput
                        :model-value="file.url"
                        readonly
                        :aria-label="trans('file_manager.files.url')"
                        class="w-full"
                    />
                    <div class="flex gap-2">
                        <UButton
                            color="neutral"
                            variant="subtle"
                            icon="i-lucide-copy"
                            :label="trans('file_manager.files.copy_url')"
                            @click="copyUrl"
                        />
                        <UButton
                            color="neutral"
                            variant="ghost"
                            icon="i-lucide-external-link"
                            :label="trans('file_manager.files.open')"
                            :to="file.url"
                            target="_blank"
                        />
                    </div>
                </div>

                <form
                    v-if="file.is_image"
                    class="flex flex-col gap-3"
                    @submit.prevent="save"
                >
                    <UFormField
                        :label="trans('file_manager.files.alt_text')"
                        :help="trans('file_manager.files.alt_text_help')"
                        name="alt_text"
                        :error="form.errors.alt_text"
                    >
                        <UTextarea
                            v-model="form.alt_text"
                            :disabled="!canEdit"
                            :rows="2"
                            class="w-full"
                        />
                    </UFormField>
                    <UButton
                        v-if="canEdit"
                        type="submit"
                        class="w-fit"
                        :loading="form.processing"
                        :disabled="!form.isDirty"
                        :label="trans('file_manager.files.save')"
                    />
                </form>
            </div>
        </template>
    </USlideover>
</template>
