<script setup lang="ts">
import { VueDraggable } from 'vue-draggable-plus';
import type { MenuItem, MenuPageOption } from '@/types';

/**
 * One level of a menu's items, nesting itself for each item's children. Items can be dragged
 * within a level or between levels (they share a drag group).
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

const emit = defineEmits<{
    edit: [item: MenuItem, path: string];
    addChild: [item: MenuItem, path: string];
}>();

const { trans } = useTrans();

function linkSummary(item: MenuItem): string {
    if (item.link_type === 'page') {
        return (
            props.pages.find((page) => page.value === item.page_id)?.url ??
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

function remove(index: number) {
    items.value = items.value.filter((_, itemIndex) => itemIndex !== index);
}
</script>

<template>
    <VueDraggable
        v-model="items"
        :disabled="readonly"
        group="cms-menu-items"
        handle=".drag-handle"
        :animation="150"
        class="flex min-h-2 flex-col gap-2"
    >
        <div v-for="(item, index) in items" :key="item._key">
            <div
                class="border-default bg-default flex items-center gap-2 rounded-md border px-2 py-1.5"
                :class="{ 'border-error': hasErrors(`${path}.${index}`) }"
            >
                <UButton
                    v-if="!readonly"
                    class="drag-handle cursor-grab"
                    color="neutral"
                    variant="ghost"
                    size="sm"
                    icon="i-lucide-grip-vertical"
                    :aria-label="trans('cms.menus.items.drag')"
                />
                <UIcon
                    :name="
                        item.link_type === 'page'
                            ? 'i-lucide-file-text'
                            : 'i-lucide-link'
                    "
                    class="text-muted size-4 shrink-0"
                />
                <span class="min-w-0 flex-1 truncate text-sm">
                    <span class="font-medium">{{ item.label }}</span>
                    <span class="text-muted ms-2 font-mono text-xs">{{
                        linkSummary(item)
                    }}</span>
                    <UIcon
                        v-if="item.target === '_blank'"
                        name="i-lucide-external-link"
                        class="text-muted ms-1 size-3"
                        :aria-label="trans('cms.menus.items.opens_new_tab')"
                    />
                </span>

                <template v-if="!readonly">
                    <UButton
                        v-if="depth < maxDepth"
                        color="neutral"
                        variant="ghost"
                        size="sm"
                        icon="i-lucide-corner-down-right"
                        :aria-label="trans('cms.menus.items.add_child')"
                        @click="emit('addChild', item, `${path}.${index}`)"
                    />
                    <UButton
                        color="neutral"
                        variant="ghost"
                        size="sm"
                        icon="i-lucide-pencil"
                        :aria-label="trans('cms.menus.items.edit')"
                        @click="emit('edit', item, `${path}.${index}`)"
                    />
                    <UButton
                        color="neutral"
                        variant="ghost"
                        size="sm"
                        icon="i-lucide-trash"
                        :aria-label="trans('cms.menus.items.remove')"
                        @click="remove(index)"
                    />
                </template>
            </div>

            <!-- Children, indented; an empty level still accepts dropped items -->
            <MenuItemTree
                v-if="depth < maxDepth"
                v-model="item.children"
                class="ms-6 mt-2"
                :path="`${path}.${index}.children`"
                :depth="depth + 1"
                :max-depth="maxDepth"
                :pages="pages"
                :errors="errors"
                :readonly="readonly"
                @edit="(child, childPath) => emit('edit', child, childPath)"
                @add-child="
                    (child, childPath) => emit('addChild', child, childPath)
                "
            />
        </div>
    </VueDraggable>
</template>
