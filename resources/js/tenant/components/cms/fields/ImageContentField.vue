<script setup lang="ts">
import FileField from '@/tenant/components/file-manager/FileField.vue';
import type { TemplateFieldData } from '@/types';

/** An image from the File Manager, stored by file id with its own alt text. */
type ImageValue = { file_id: number | null; alt: string | null };

defineProps<{
    field: TemplateFieldData;
    disabled?: boolean;
}>();

const value = defineModel<ImageValue | null>({ default: null });

const { trans } = useTrans();

const fileId = computed({
    get: () => value.value?.file_id ?? null,
    set: (file_id: number | null) => {
        value.value =
            file_id === null
                ? null
                : { file_id, alt: value.value?.alt ?? null };
    },
});

const alt = computed({
    get: () => value.value?.alt ?? '',
    set: (text: string) => {
        if (value.value) {
            value.value = { ...value.value, alt: text };
        }
    },
});
</script>

<template>
    <div class="flex flex-col gap-3">
        <FileField v-model="fileId" images-only :disabled="disabled" />

        <UFormField
            v-if="fileId !== null"
            :label="trans('cms.content.image.alt')"
            :help="disabled ? undefined : trans('cms.content.image.alt_help')"
        >
            <UInput
                v-model="alt"
                :disabled="disabled"
                maxlength="255"
                class="w-full"
            />
        </UFormField>
    </div>
</template>
