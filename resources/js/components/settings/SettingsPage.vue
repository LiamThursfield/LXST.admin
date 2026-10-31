<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
// Imported explicitly: the central app doesn't use this yet, so its generated component types lack it.
import UKbd from '@nuxt/ui/components/Kbd.vue';

/**
 * A settings page inside a settings sub-layout: a header with the title, a description and any
 * secondary actions, then the page's setting rows (see `SettingsRow`).
 *
 * A page whose rows save together passes the `id` of the `<form>` around them as `formId`, and
 * gets a save bar that sticks to the bottom: "Unsaved changes", Discard and Save. ⌘S / Ctrl+S
 * saves too, and leaving with unsaved changes asks first. Pages that save each change as it's
 * made (e.g. API tokens) leave `formId` out.
 */
const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        /** The user can see the settings but not change them: no save bar. */
        readonly?: boolean;
        formId?: string;
        isDirty?: boolean;
        processing?: boolean;
    }>(),
    {
        description: undefined,
        readonly: false,
        formId: undefined,
        isDirty: false,
        processing: false,
    },
);

const emit = defineEmits<{
    discard: [];
}>();

defineSlots<{
    /** Secondary actions in the header, e.g. "Send a test email". */
    actions?(): unknown;
    default(): unknown;
}>();

const { trans } = useTrans();

const isSaveable = computed(() => Boolean(props.formId) && !props.readonly);

function save() {
    if (!isSaveable.value || props.processing) {
        return;
    }

    (
        document.getElementById(props.formId!) as HTMLFormElement | null
    )?.requestSubmit();
}

defineShortcuts({
    meta_s: {
        usingInput: true,
        handler: save,
    },
});

const { isConfirmOpen, leave, stay } = useUnsavedChanges(
    () => isSaveable.value && props.isDirty && !props.processing,
);
</script>

<template>
    <div class="flex flex-col">
        <Head :title="title" />

        <div class="flex flex-wrap items-start gap-3 pb-6">
            <div class="min-w-0 flex-1 basis-80">
                <h2 class="text-highlighted text-xl font-semibold">
                    {{ title }}
                </h2>
                <p
                    v-if="readonly || description"
                    class="text-muted mt-1 text-sm"
                >
                    {{
                        readonly
                            ? trans('common.settings.read_only')
                            : description
                    }}
                </p>
            </div>
            <div v-if="$slots.actions" class="flex flex-wrap gap-2">
                <slot name="actions" />
            </div>
        </div>

        <slot />

        <!-- Reaches into the panel's bottom padding, so nothing scrolls past below it -->
        <div
            v-if="isSaveable"
            class="bg-default border-default sticky -bottom-4 z-10 -mb-4 mt-2 flex flex-wrap items-center justify-end gap-2 border-t py-3 sm:-bottom-6 sm:-mb-6"
        >
            <span
                v-if="isDirty"
                class="text-warning me-auto flex items-center gap-1.5 text-sm"
            >
                <span class="bg-warning size-2 rounded-full" />
                {{ trans('common.editor.unsaved') }}
            </span>
            <UButton
                color="neutral"
                variant="ghost"
                :disabled="!isDirty || processing"
                :label="trans('common.settings.discard')"
                @click="emit('discard')"
            />
            <UButton
                type="submit"
                :form="formId"
                color="primary"
                :loading="processing"
                :label="trans('common.settings.save')"
            >
                <template #trailing>
                    <span class="hidden items-center gap-0.5 sm:flex">
                        <UKbd value="meta" size="sm" />
                        <UKbd value="s" size="sm" />
                    </span>
                </template>
            </UButton>
        </div>

        <UModal
            v-model:open="isConfirmOpen"
            :title="trans('common.editor.leave_title')"
            :description="trans('common.editor.leave_description')"
        >
            <template #body>
                <div class="flex justify-end gap-2">
                    <UButton
                        color="neutral"
                        variant="subtle"
                        :label="trans('common.editor.stay')"
                        @click="stay"
                    />
                    <UButton
                        color="error"
                        :label="trans('common.editor.leave')"
                        @click="leave"
                    />
                </div>
            </template>
        </UModal>
    </div>
</template>
