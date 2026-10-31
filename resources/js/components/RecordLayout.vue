<script setup lang="ts">
import type { Method } from '@inertiajs/core';
import { Head } from '@inertiajs/vue3';
import type {
    BreadcrumbItem,
    DropdownMenuItem,
    NavigationMenuItem,
} from '@nuxt/ui';
// Imported explicitly: the central app doesn't use this yet, so its generated component types lack it.
import UBreadcrumb from '@nuxt/ui/components/Breadcrumb.vue';
import RowActions from '@/components/RowActions.vue';

/** A tab of related records, with how many there are. */
export type RecordTab = {
    value: string;
    label: string;
    count?: number;
};

/** A record action, as `HasActions` gives it (see backend `Action`). */
type RecordAction = {
    label: string;
    url: string;
    method: Method;
    icon?: string;
    requireConfirmation: boolean;
    confirmationMessage?: string;
};

/**
 * The page for one record: a navbar with breadcrumbs, a subtitle, badges, a more-actions menu
 * and Edit; tabs of related records, each with a count; and a body with the chosen tab in the
 * main column and a sidebar of read-only facts (see `RecordFacts`), which stacks below the main
 * column on narrow screens.
 *
 * The more-actions menu is either the record's `actions` from the server (its Edit action
 * becomes the Edit button, and View is left out) or a `menu` of the page's own items.
 */
const props = withDefaults(
    defineProps<{
        /** The record's name, for the browser tab. */
        title: string;
        /** The path to this page; the last item is the record. */
        breadcrumbs: BreadcrumbItem[];
        /** Shown after the breadcrumbs, e.g. a song's artist. */
        subtitle?: string | null;
        tabs?: RecordTab[];
        actions?: RecordAction[];
        menu?: DropdownMenuItem[] | DropdownMenuItem[][];
        /** Where Edit goes, when there are no `actions` to find it in. */
        editHref?: string | null;
        /** Shows Edit as a button that emits `edit`, e.g. to edit in a modal. */
        editable?: boolean;
        editLabel?: string;
    }>(),
    {
        subtitle: null,
        tabs: () => [],
        actions: () => [],
        menu: () => [],
        editHref: null,
        editable: false,
        editLabel: undefined,
    },
);

defineEmits<{
    edit: [];
}>();

const tab = defineModel<string>('tab');

defineSlots<{
    /** Status badges after the title. */
    badges?(): unknown;
    /** Navbar buttons before the more-actions menu. */
    actions?(): unknown;
    /** Above the body's columns, e.g. a warning that the record is disabled. */
    notice?(): unknown;
    default(): unknown;
    sidebar?(): unknown;
}>();

const { trans } = useTrans();

if (tab.value === undefined && props.tabs.length) {
    tab.value = props.tabs[0].value;
}

const editUrl = computed(
    () =>
        props.editHref ??
        props.actions.find(
            (action) => action.method === 'get' && action.url.endsWith('/edit'),
        )?.url ??
        null,
);

/** The server actions that do something, rather than go to a page. */
const menuActions = computed(() =>
    props.actions.filter((action) => action.method !== 'get'),
);

const tabItems = computed<NavigationMenuItem[]>(() =>
    props.tabs.map((item) => ({
        label: item.label,
        active: tab.value === item.value,
        onSelect: () => {
            tab.value = item.value;
        },
        badge:
            item.count !== undefined
                ? {
                      label: String(item.count),
                      color: 'neutral',
                      variant: 'subtle',
                  }
                : undefined,
    })),
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
                        <span
                            v-if="subtitle"
                            class="text-muted hidden truncate text-sm font-normal sm:inline"
                        >
                            {{ subtitle }}
                        </span>
                        <div
                            v-if="$slots.badges"
                            class="hidden shrink-0 items-center gap-2 sm:flex"
                        >
                            <slot name="badges" />
                        </div>
                    </div>
                </template>

                <template #right>
                    <slot name="actions" />

                    <RowActions
                        v-if="menuActions.length"
                        :actions="menuActions"
                        :max-visible="0"
                        toolbar
                    />

                    <UDropdownMenu
                        v-if="menu.length"
                        :items="menu"
                        :content="{ align: 'end' }"
                    >
                        <UButton
                            color="neutral"
                            variant="outline"
                            icon="i-lucide-ellipsis"
                            square
                            :aria-label="trans('common.actions.more')"
                        />
                    </UDropdownMenu>

                    <UButton
                        v-if="editUrl"
                        as="Link"
                        :href="editUrl"
                        color="primary"
                        icon="i-lucide-pencil"
                        :label="editLabel ?? trans('common.actions.edit')"
                    />
                    <UButton
                        v-else-if="editable"
                        color="primary"
                        icon="i-lucide-pencil"
                        :label="editLabel ?? trans('common.actions.edit')"
                        @click="$emit('edit')"
                    />
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

            <slot name="notice" />

            <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
                <div class="flex min-w-0 flex-1 flex-col gap-6">
                    <slot />
                </div>

                <aside
                    v-if="$slots.sidebar"
                    class="flex w-full shrink-0 flex-col gap-6 lg:w-80 xl:w-96"
                >
                    <slot name="sidebar" />
                </aside>
            </div>
        </template>
    </UDashboardPanel>
</template>
