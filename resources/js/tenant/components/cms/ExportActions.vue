<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';

/**
 * Bulk actions for a list's ticked rows: copy them as portable JSON, or download it. Pasted into the
 * import page of another environment, it moves them there.
 */
const props = defineProps<{
    /** Where to get the JSON for the ticked rows. */
    url: () => string;
    filename: string;
}>();

const { trans } = useTrans();

const toast = useToast();

const exportRequest = useHttp();

async function exportedJson(): Promise<string | null> {
    try {
        return JSON.stringify(await exportRequest.get(props.url()), null, 2);
    } catch {
        toast.add({
            title: trans('cms.export.failed'),
            icon: 'i-lucide-circle-alert',
            color: 'error',
        });

        return null;
    }
}

async function copyJson() {
    const json = await exportedJson();

    if (json !== null) {
        await navigator.clipboard.writeText(json);
        toast.add({
            title: trans('cms.export.copied'),
            icon: 'i-lucide-check',
            color: 'success',
        });
    }
}

async function downloadJson() {
    const json = await exportedJson();

    if (json !== null) {
        const objectUrl = URL.createObjectURL(
            new Blob([json], { type: 'application/json' }),
        );
        const link = document.createElement('a');

        link.href = objectUrl;
        link.download = props.filename;
        link.click();
        URL.revokeObjectURL(objectUrl);
    }
}
</script>

<template>
    <UButton
        color="neutral"
        variant="subtle"
        size="sm"
        icon="i-lucide-copy"
        :loading="exportRequest.processing"
        :label="trans('cms.export.copy')"
        @click="copyJson"
    />
    <UButton
        color="neutral"
        variant="subtle"
        size="sm"
        icon="i-lucide-download"
        :loading="exportRequest.processing"
        :label="trans('cms.export.download')"
        @click="downloadJson"
    />
</template>
