<script setup lang="ts">
import { VueDraggable } from 'vue-draggable-plus';
import type { MenuItem, MenuPageOption } from '@/types';

/**
 * One level of a menu's items, nesting itself for each item's children. Each item is a compact
 * row (its label, link, and markers for a new tab and for errors), and choosing one selects it,
 * so its settings can be shown beside the tree. Items can be dragged within a level or between
 * levels (they share a drag group).
 */
const props = withDefaults(
    defineProps<{
        /** Where these items' errors are, e.g. `menu_items` or `menu_items.0.children`. */
        path: string;
        depth: number;
        maxDepth: number;
        pages: MenuPageOption[];
        errors?: Record<string, string | undefined>;
        readonly?: boolean;
    }>(),
    {
        errors: () => ({}),
        readonly: false,
    },
);

const items = defineModel<MenuItem[]>({ required: true });

/** The chosen item's `_key`, shared by every level of the tree. */
const selected = defineModel<number | null>('selected', { default: null });

const { trans } = useTrans();

/** The page's name (or that none is chosen yet), or the URL. */
function linkSummary(item: MenuItem): string {
    if (item.link_type === 'page') {
        if (item.page_id === null) {
            return trans('cms.menus.items.no_page');
        }

        return (
            props.pages.find((page) => page.value === item.page_id)?.label ??
            trans('cms.menus.items.missing_page')
        );
    }

    return item.url ?? '';
}

function hasErrors(itemPath: string): boolean {
    return Object.keys(props.errors).some(
        (key) => key === itemPath || key.startsWith(`${itemPath}.`),
    );
}
</script>

<template>
    <VueDraggable
        v-model="items"
        :disabled="readonly"
        group="cms-menu-items"
        handle=".drag-handle"
        :animation="150"
        class="flex min-h-2 flex-col gap-0.5"
    >
        <div v-for="(item, index) in items" :key="item._key">
            <div
                class="flex items-center gap-1 rounded-md"
                :class="
                    selected === item._key
                        ? 'bg-primary/10 ring-primary/40 ring'
                        : 'hover:bg-elevated'
                "
            >
                <UButton
                    v-if="!readonly"
                    class="drag-handle cursor-grab"
                    color="neutral"
                    variant="link"
                    size="sm"
                    icon="i-lucide-grip-vertical"
                    :aria-label="trans('cms.menus.items.drag')"
                />
                <button
                    type="button"
                    class="focus-visible:outline-primary flex min-w-0 flex-1 items-center gap-2.5 rounded-md py-1.5 pr-2 text-left focus-visible:outline-2"
                    :class="{ 'pl-2': readonly }"
                    :aria-pressed="selected === item._key"
                    @click="selected = item._key ?? null"
                >
                    <span
                        class="bg-elevated text-toned flex size-7 shrink-0 items-center justify-center rounded-md"
                    >
                        <UIcon
                            :name="
                                item.link_type === 'page'
                                    ? 'i-lucide-file-text'
                                    : 'i-lucide-link'
                            "
                            class="size-4"
                        />
                    </span>
                    <span class="flex min-w-0 flex-1 flex-col">
                        <span
                            class="text-highlighted truncate text-sm font-medium"
                        >
                            {{ item.label }}
                        </span>
                        <span
                            class="text-muted truncate text-xs"
                            :class="{ 'font-mono': item.link_type === 'url' }"
                        >
                            {{ linkSummary(item) }}
                        </span>
                    </span>
                    <UIcon
                        v-if="item.target === '_blank'"
                        name="i-lucide-external-link"
                        class="text-muted size-4 shrink-0"
                        :aria-label="trans('cms.menus.items.opens_new_tab')"
                    />
                    <span
                        v-if="hasErrors(`${path}.${index}`)"
                        class="bg-error size-2 shrink-0 rounded-full"
                        role="img"
                        :aria-label="trans('common.builder.has_errors')"
                    />
                </button>
            </div>

            <!-- Children, indented; an empty level still accepts dropped items -->
            <MenuItemTree
                v-if="depth < maxDepth"
                v-model="item.children"
                v-model:selected="selected"
                class="ms-6"
                :path="`${path}.${index}.children`"
                :depth="depth + 1"
                :max-depth="maxDepth"
                :pages="pages"
                :errors="errors"
                :readonly="readonly"
            />
        </div>
    </VueDraggable>
</template>
