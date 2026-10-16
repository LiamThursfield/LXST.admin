<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { show } from '@/actions/App/Http/Controllers/Tenant/Admin/FileManager/FilePickerController';
import type { FileManagerFile } from '@/types';
import FilePickerModal from './FilePickerModal.vue';

/**
 * A form field for choosing a File Manager file. The value is the file's id, so
 * renaming or moving the file later doesn't break whatever uses it.
 */
const props = withDefaults(
    defineProps<{
        imagesOnly?: boolean;
        disabled?: boolean;
    }>(),
    {
        imagesOnly: false,
        disabled: false,
    },
);

const fileId = defineModel<number | null>({ default: null });

const { trans } = useTrans();

const file = ref<FileManagerFile | null>(null);
const missing = ref(false);
const pickerOpen = ref(false);
const loader = useHttp();

// Load the chosen file's details whenever the id changes to one we don't have
watch(
    fileId,
    async (id) => {
        missing.value = false;

        if (id === null) {
            file.value = null;
            return;
        }

        if (file.value?.id === id) {
            return;
        }

        try {
            const response = (await loader.get(show.url(id))) as {
                data: FileManagerFile;
            };
            file.value = response.data;
        } catch {
            file.value = null;
            missing.value = true;
        }
    },
    { immediate: true },
);

function select(selected: FileManagerFile) {
    file.value = selected;
    fileId.value = selected.id;
}

function clear() {
    file.value = null;
    fileId.value = null;
}
</script>

<template>
    <div class="border-default flex items-center gap-3 rounded-md border p-2">
        <div
            class="bg-elevated flex size-14 shrink-0 items-center justify-center overflow-hidden rounded"
        >
            <img
                v-if="file?.is_image"
                :src="file.url"
                :alt="file.alt_text ?? ''"
                class="size-full object-contain"
            />
            <UIcon
                v-else
                :name="
                    file
                        ? 'i-lucide-file'
                        : imagesOnly
                          ? 'i-lucide-image'
                          : 'i-lucide-file-question'
                "
                class="text-muted size-6"
            />
        </div>

        <div class="min-w-0 flex-1 text-sm">
            <p v-if="file" class="truncate">{{ file.name }}</p>
            <p v-else-if="missing" class="text-error">
                {{ trans('file_manager.picker.missing') }}
            </p>
            <p v-else class="text-muted">
                {{
                    imagesOnly
                        ? trans('file_manager.picker.none_image')
                        : trans('file_manager.picker.none')
                }}
            </p>
        </div>

        <div v-if="!disabled" class="flex shrink-0 gap-1">
            <UButton
                size="sm"
                color="neutral"
                variant="subtle"
                :loading="loader.processing"
                :label="
                    fileId === null
                        ? trans('file_manager.picker.choose')
                        : trans('file_manager.picker.change')
                "
                @click="pickerOpen = true"
            />
            <UButton
                v-if="fileId !== null"
                size="sm"
                color="neutral"
                variant="ghost"
                icon="i-lucide-x"
                :aria-label="trans('file_manager.picker.remove')"
                @click="clear"
            />
        </div>

        <FilePickerModal
            v-model:open="pickerOpen"
            :images-only="props.imagesOnly"
            @select="select"
        />
    </div>
</template>
