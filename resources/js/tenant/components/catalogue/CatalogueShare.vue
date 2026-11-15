<script setup lang="ts">
/**
 * A public catalogue page's link and QR code, to copy, download or print (e.g. on a table, a window
 * or a flyer), and a button to print the page itself or save it as a PDF
 */
const props = defineProps<{
    url: string;
    qrCodeUrl: string;
    downloadUrl: string;
    /** The page opened with its print dialog, when it can be printed. */
    printUrl?: string;
}>();

const { trans } = useTrans();
const toast = useToast();

async function copyLink() {
    await navigator.clipboard.writeText(props.url);
    toast.add({
        title: trans('catalogue.share.copied'),
        icon: 'i-lucide-check',
        color: 'success',
    });
}
</script>

<template>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
        <img
            :src="qrCodeUrl"
            :alt="trans('catalogue.share.qr')"
            class="size-36 shrink-0 rounded-md bg-white p-1"
        />
        <div class="flex min-w-0 flex-1 flex-col gap-2">
            <div class="flex gap-2">
                <UInput
                    :model-value="url"
                    readonly
                    class="w-full font-mono"
                    :aria-label="trans('catalogue.share.link')"
                />
                <UButton
                    icon="i-lucide-copy"
                    color="neutral"
                    variant="outline"
                    :label="trans('catalogue.share.copy')"
                    @click="copyLink"
                />
            </div>
            <div class="flex flex-wrap gap-x-4">
                <UButton
                    :href="url"
                    target="_blank"
                    variant="link"
                    icon="i-lucide-external-link"
                    class="px-0"
                    :label="trans('catalogue.share.open')"
                />
                <UButton
                    :href="downloadUrl"
                    variant="link"
                    icon="i-lucide-download"
                    class="px-0"
                    :label="trans('catalogue.share.download_qr')"
                />
                <UButton
                    v-if="printUrl"
                    :href="printUrl"
                    target="_blank"
                    variant="link"
                    icon="i-lucide-printer"
                    class="px-0"
                    :label="trans('catalogue.share.print')"
                />
            </div>
            <slot />
        </div>
    </div>
</template>
