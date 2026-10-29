<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { BreadcrumbItem, NavigationMenuItem } from '@nuxt/ui';
// Imported explicitly: the central app doesn't use these yet, so its generated component types lack them.
import UBreadcrumb from '@nuxt/ui/components/Breadcrumb.vue';
import UKbd from '@nuxt/ui/components/Kbd.vue';

/** A tab in the toolbar, with a count of its fields' server errors, or of what's in it. */
export type EditorTab = {
    value: string;
    label: string;
    errorCount?: number;
    /** Shown when there are no errors, e.g. how many fields a template has. */
    count?: number;
};

/**
 * The page around a create, edit or read-only form: a navbar with breadcrumbs, badges and the
 * actions (Save always in view), optional tabs in a toolbar, and a body with a main column and a
 * sidebar that sticks while scrolling (and stacks below the main column on narrow screens).
 *
 * The body is a `<form>`, so Enter in a field submits. ⌘S / Ctrl+S submits too, and leaving with
 * unsaved changes asks first. Render each tab's fields in the default slot, shown with `v-show`
 * against the `tab` model so every field stays mounted.
 *
 * A page with a form of its own (e.g. a `UForm`, for its client-side checks) passes `ownForm`, so
 * the body isn't a form as well; Save and ⌘S still emit `submit`, for it to submit its own form.
 * Put a `SubmitOnEnter` in that form, so Enter submits it.
 */
const props = withDefaults(
    defineProps<{
        /** The page's title, for the browser tab. */
        title: string;
        /** The path to this page; the last item is the current page. */
        breadcrumbs: BreadcrumbItem[];
        tabs?: EditorTab[];
        isDirty?: boolean;
        processing?: boolean;
        readonly?: boolean;
        submitLabel?: string;
        /** The page renders its own `<form>` in the default slot, so the body isn't one. */
        ownForm?: boolean;
        /** A short form without a sidebar, kept to a readable width. */
        narrow?: boolean;
    }>(),
    {
        tabs: () => [],
        isDirty: false,
        processing: false,
        readonly: false,
        submitLabel: undefined,
        ownForm: false,
        narrow: false,
    },
);

const emit = defineEmits<{
    submit: [];
}>();

const tab = defineModel<string>('tab');

defineSlots<{
    /** Status badges beside the breadcrumbs. */
    badges?(): unknown;
    /** Navbar actions before Save, e.g. a link to view the page. */
    actions?(): unknown;
    default(): unknown;
    sidebar?(): unknown;
}>();

const { trans } = useTrans();

/** The tabs as a menu, the same as the settings pages' tabs. */
const tabItems = computed<NavigationMenuItem[]>(() =>
    props.tabs.map((item) => ({
        label: item.label,
        active: tab.value === item.value,
        onSelect: () => {
            tab.value = item.value;
        },
        badge: item.errorCount
            ? {
                  label: String(item.errorCount),
                  color: 'error',
                  variant: 'subtle',
              }
            : item.count !== undefined
              ? {
                    label: String(item.count),
                    color: 'neutral',
                    variant: 'subtle',
                }
              : undefined,
    })),
);

/** The body's own form submitting, e.g. from Enter in a field. */
function onFormSubmit(event: Event) {
    event.preventDefault();
    submit();
}

function submit() {
    if (props.readonly || props.processing) {
        return;
    }

    emit('submit');
}

defineShortcuts({
    meta_s: {
        usingInput: true,
        handler: submit,
    },
});

const { isConfirmOpen, leave, stay } = useUnsavedChanges(
    () => props.isDirty && !props.readonly && !props.processing,
);
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar>
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>

                <template #title>
                    <div class="flex min-w-0 items-center gap-2">
                        <UBreadcrumb :items="breadcrumbs" class="min-w-0" />
                        <!-- Hidden on phones, where the breadcrumbs need the room -->
                        <div
                            v-if="$slots.badges"
                            class="hidden shrink-0 items-center gap-2 sm:flex"
                        >
                            <slot name="badges" />
                        </div>
                    </div>
                </template>

                <template #right>
                    <span
                        v-if="isDirty && !readonly"
                        class="text-warning hidden items-center gap-1.5 text-sm sm:flex"
                    >
                        <span class="bg-warning size-2 rounded-full" />
                        {{ trans('common.editor.unsaved') }}
                    </span>

                    <slot name="actions" />

                    <UButton
                        v-if="!readonly"
                        color="primary"
                        :loading="processing"
                        :label="submitLabel ?? trans('common.editor.save')"
                        @click="submit"
                    >
                        <template #trailing>
                            <span class="hidden items-center gap-0.5 sm:flex">
                                <UKbd value="meta" size="sm" />
                                <UKbd value="s" size="sm" />
                            </span>
                        </template>
                    </UButton>
                </template>
            </UDashboardNavbar>

            <UDashboardToolbar v-if="tabs.length">
                <!-- NOTE: The `-mx-1` class is used to align with the `DashboardSidebarCollapse` button here. -->
                <UNavigationMenu
                    :items="tabItems"
                    highlight
                    class="-mx-1 flex-1"
                />
            </UDashboardToolbar>
        </template>

        <template #body>
            <Head :title="title" />

            <!-- A page with its own form gets no listener here, as its submit event bubbles up -->
            <component
                :is="ownForm ? 'div' : 'form'"
                class="flex flex-col gap-6 lg:flex-row lg:items-start"
                v-on="ownForm ? {} : { submit: onFormSubmit }"
            >
                <div
                    class="flex min-w-0 flex-1 flex-col gap-6"
                    :class="{ 'max-w-4xl': narrow }"
                >
                    <slot />
                </div>

                <aside
                    v-if="$slots.sidebar"
                    class="flex w-full shrink-0 flex-col gap-6 lg:sticky lg:top-0 lg:max-h-[calc(100dvh-10rem)] lg:w-80 lg:overflow-y-auto xl:w-96"
                >
                    <slot name="sidebar" />
                </aside>

                <SubmitOnEnter v-if="!ownForm" />
            </component>

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
        </template>
    </UDashboardPanel>
</template>
