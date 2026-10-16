<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import {
    index,
    store,
} from '@/actions/App/Http/Controllers/Tenant/Admin/FileManager/FilePickerController';
import { formatBytes } from '@/lib/files';
import type { FileManagerFile, FilePickerFolder } from '@/types';

const props = withDefaults(
    defineProps<{
        /** Only show (and accept) images, e.g. when choosing a logo. */
        imagesOnly?: boolean;
    }>(),
    {
        imagesOnly: false,
    },
);

const emit = defineEmits<{
    select: [file: FileManagerFile];
}>();

const open = defineModel<boolean>('open', { required: true });

const { trans } = useTrans();

const contents = ref<FilePickerFolder | null>(null);
const loadFailed = ref(false);
const loader = useHttp();

const upload = useHttp({
    folder_id: null as number | null,
    files: [] as File[],
});
const showUpload = ref(false);

/** Opens a folder (or the top level) inside the picker. */
async function openFolder(folderId: number | null) {
    loadFailed.value = false;

    try {
        contents.value = (await loader.get(
            index.url({
                query: {
                    ...(folderId ? { folder: String(folderId) } : {}),
                    ...(props.imagesOnly ? { images_only: '1' } : {}),
                },
            }),
        )) as FilePickerFolder;
    } catch {
        loadFailed.value = true;
    }
}

// Start at the top level each time the picker opens
watch(open, (isOpen) => {
    if (isOpen) {
        showUpload.value = false;
        openFolder(null);
    }
});

function choose(file: FileManagerFile) {
    emit('select', file);
    open.value = false;
}

const accept = computed(() => (props.imagesOnly ? 'image/*' : undefined));

/** Upload errors for individual files, which name the file. */
const uploadErrors = computed(() =>
    Object.entries(upload.errors as Record<string, string>)
        .filter(([key]) => key === 'files' || key.startsWith('files.'))
        .map(([, message]) => message),
);

async function submitUpload() {
    upload.folder_id = contents.value?.currentFolder?.id ?? null;

    try {
        await upload.post(store.url());
        upload.reset();
        showUpload.value = false;
        await openFolder(upload.folder_id);
    } catch {
        // Validation errors are shown from upload.errors
    }
}

/** Breadcrumbs back to the top level. They are buttons, not links, because the picker browses in place. */
const breadcrumbs = computed(() => [
    { id: null, name: trans('file_manager.top_level') },
    ...(contents.value?.breadcrumbs ?? []),
]);
</script>

<template>
    <UModal
        v-model:open="open"
        :title="
            imagesOnly
                ? trans('file_manager.picker.title_image')
                : trans('file_manager.picker.title')
        "
        :ui="{ content: 'sm:max-w-3xl' }"
    >
        <template #body>
            <div class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <nav
                        aria-label="breadcrumb"
                        class="flex flex-wrap items-center gap-1 text-sm"
                    >
                        <template
                            v-for="(crumb, index) in breadcrumbs"
                            :key="crumb.id ?? 'top'"
                        >
                            <UIcon
                                v-if="index > 0"
                                name="i-lucide-chevron-right"
                                class="text-muted size-4"
                            />
                            <UButton
                                size="sm"
                                variant="link"
                                :color="
                                    index === breadcrumbs.length - 1
                                        ? 'primary'
                                        : 'neutral'
                                "
                                :icon="
                                    index === 0
                                        ? 'i-lucide-hard-drive'
                                        : undefined
                                "
                                :label="crumb.name"
                                class="px-1"
                                @click="openFolder(crumb.id)"
                            />
                        </template>
                    </nav>
                    <UButton
                        v-if="contents?.canUpload"
                        size="sm"
                        color="neutral"
                        variant="subtle"
                        icon="i-lucide-upload"
                        :label="trans('file_manager.picker.upload')"
                        @click="showUpload = !showUpload"
                    />
                </div>

                <form
                    v-if="showUpload"
                    class="flex flex-col gap-2"
                    @submit.prevent="submitUpload"
                >
                    <UFileUpload
                        v-model="upload.files"
                        multiple
                        layout="list"
                        class="w-full"
                        :accept="accept"
                        :label="trans('file_manager.files.upload_title')"
                    />
                    <p
                        v-for="message in uploadErrors"
                        :key="message"
                        class="text-error text-sm"
                    >
                        {{ message }}
                    </p>
                    <UButton
                        type="submit"
                        class="w-fit"
                        :disabled="!upload.files.length"
                        :loading="upload.processing"
                        :label="trans('file_manager.files.upload')"
                    />
                </form>

                <p v-if="loadFailed" class="text-error text-sm">
                    {{ trans('file_manager.picker.error') }}
                </p>

                <div
                    v-else-if="loader.processing && !contents"
                    class="flex justify-center py-8"
                >
                    <UIcon
                        name="i-lucide-loader-circle"
                        class="text-muted size-6 animate-spin"
                    />
                </div>

                <template v-else-if="contents">
                    <p
                        v-if="
                            !contents.subfolders.length &&
                            !contents.files.length
                        "
                        class="text-muted text-sm"
                    >
                        {{
                            imagesOnly
                                ? trans('file_manager.picker.empty_images')
                                : trans('file_manager.picker.empty')
                        }}
                    </p>

                    <ul
                        v-else
                        class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4"
                        :class="{ 'opacity-60': loader.processing }"
                    >
                        <li
                            v-for="folder in contents.subfolders"
                            :key="`folder-${folder.id}`"
                        >
                            <button
                                type="button"
                                class="border-default hover:bg-elevated/50 flex h-full w-full flex-col items-center gap-2 rounded-md border p-3 text-sm"
                                @click="openFolder(folder.id)"
                            >
                                <UIcon
                                    name="i-lucide-folder"
                                    class="text-primary size-10"
                                />
                                <span class="w-full truncate">{{
                                    folder.name
                                }}</span>
                            </button>
                        </li>

                        <li
                            v-for="file in contents.files"
                            :key="`file-${file.id}`"
                        >
                            <button
                                type="button"
                                class="border-default hover:border-primary hover:bg-elevated/50 flex h-full w-full flex-col items-center gap-2 rounded-md border p-3 text-sm"
                                @click="choose(file)"
                            >
                                <img
                                    v-if="file.is_image"
                                    :src="file.url"
                                    :alt="file.alt_text ?? ''"
                                    class="h-20 w-full rounded object-contain"
                                />
                                <UIcon
                                    v-else
                                    name="i-lucide-file"
                                    class="text-muted size-10"
                                />
                                <span class="w-full truncate">{{
                                    file.name
                                }}</span>
                                <span class="text-muted text-xs">{{
                                    formatBytes(file.size)
                                }}</span>
                            </button>
                        </li>
                    </ul>
                </template>
            </div>
        </template>
    </UModal>
</template>
