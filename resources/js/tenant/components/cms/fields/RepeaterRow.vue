<script setup lang="ts">
import type { DropdownMenuItem } from '@nuxt/ui';
import { useFileDetails } from '@/tenant/components/file-manager/useFileDetails';
import type { ContentValues, TemplateFieldData } from '@/types';
import ContentEditor from '../ContentEditor.vue';
import { useCollapsible } from '../contentState';
import { textValues } from '../contentSummary';

/**
 * One row of a repeater, collapsed to a header: a thumbnail from its first image field, a title
 * and subtitle from its text fields, and a menu of actions, which also gives keyboard users a
 * way to reorder without dragging.
 */
const props = defineProps<{
    /** The repeater template's fields. */
    fields: TemplateFieldData[];
    index: number;
    count: number;
    /** Where this row's errors are, e.g. `content.slides.2`. */
    prefix: string;
    /** Remembers whether it's open, by the row's `_key`, e.g. `content.slides.7`. */
    stateKey: string;
    errors?: Record<string, string | undefined>;
    disabled?: boolean;
    canAdd: boolean;
}>();

const emit = defineEmits<{
    duplicate: [];
    moveUp: [];
    moveDown: [];
    addBelow: [];
    remove: [];
}>();

const row = defineModel<ContentValues>({ required: true });

const { trans } = useTrans();

/** The first error in this row, shown in its header. */
const error = computed(
    () =>
        Object.entries(props.errors ?? {}).find(
            ([key, message]) => message && key.startsWith(`${props.prefix}.`),
        )?.[1],
);

const { open, toggle } = useCollapsible(() => props.stateKey, {
    openByDefault: false,
    hasError: () => !!error.value,
});

const bodyId = useId();

const texts = computed(() => textValues(props.fields, row.value));

const title = computed(
    () =>
        texts.value[0] ??
        trans('cms.content.repeater.item', { number: props.index + 1 }),
);

const imageField = computed(() =>
    props.fields.find((field) => field.type === 'image'),
);

const image = useFileDetails(() =>
    imageField.value ? row.value[imageField.value.slug]?.file_id : null,
);

const menuItems = computed<DropdownMenuItem[][]>(() => [
    [
        {
            label: trans('cms.content.repeater.duplicate'),
            icon: 'i-lucide-copy',
            disabled: !props.canAdd,
            onSelect: () => emit('duplicate'),
        },
        {
            label: trans('cms.content.repeater.move_up'),
            icon: 'i-lucide-chevron-up',
            disabled: props.index === 0,
            onSelect: () => emit('moveUp'),
        },
        {
            label: trans('cms.content.repeater.move_down'),
            icon: 'i-lucide-chevron-down',
            disabled: props.index === props.count - 1,
            onSelect: () => emit('moveDown'),
        },
        {
            label: trans('cms.content.repeater.add_below'),
            icon: 'i-lucide-plus',
            disabled: !props.canAdd,
            onSelect: () => emit('addBelow'),
        },
    ],
    [
        {
            label: trans('cms.content.repeater.remove'),
            icon: 'i-lucide-trash',
            color: 'error',
            onSelect: () => emit('remove'),
        },
    ],
]);
</script>

<template>
    <div
        class="rounded-md border"
        :class="error ? 'border-error bg-error/5' : 'border-default bg-default'"
        v-bind="error ? { 'data-content-error': '' } : {}"
    >
        <div class="flex items-center gap-2 px-2 py-1.5">
            <UButton
                v-if="!disabled"
                class="drag-handle cursor-grab"
                color="neutral"
                variant="ghost"
                size="sm"
                icon="i-lucide-grip-vertical"
                :aria-label="trans('cms.content.repeater.drag')"
            />

            <button
                type="button"
                class="focus-visible:outline-primary flex min-w-0 flex-1 items-center gap-2.5 rounded py-1 text-left focus-visible:outline-2"
                :aria-expanded="open"
                :aria-controls="bodyId"
                @click="toggle"
            >
                <span
                    v-if="imageField"
                    class="bg-elevated flex h-7 w-10 shrink-0 items-center justify-center overflow-hidden rounded"
                >
                    <img
                        v-if="image?.is_image"
                        :src="image.url"
                        alt=""
                        class="size-full object-cover"
                    />
                    <UIcon v-else name="i-lucide-image" class="text-dimmed" />
                </span>
                <span
                    class="min-w-0 shrink truncate text-sm font-medium"
                    :class="texts[0] ? 'text-highlighted' : 'text-muted italic'"
                >
                    {{ title }}
                </span>
                <span
                    class="min-w-0 flex-1 truncate text-sm"
                    :class="error ? 'text-error' : 'text-muted'"
                >
                    {{ error ?? texts[1] }}
                </span>
                <UIcon
                    name="i-lucide-chevron-down"
                    class="text-muted size-4 shrink-0 transition-transform"
                    :class="{ 'rotate-180': open }"
                />
            </button>

            <UDropdownMenu
                v-if="!disabled"
                :items="menuItems"
                :content="{ align: 'end' }"
            >
                <UButton
                    color="neutral"
                    variant="ghost"
                    size="sm"
                    icon="i-lucide-ellipsis"
                    :aria-label="
                        trans('cms.content.repeater.actions', { item: title })
                    "
                />
            </UDropdownMenu>
        </div>

        <div
            v-show="open"
            :id="bodyId"
            class="border-default border-t p-3 sm:p-4"
        >
            <ContentEditor
                v-model="row"
                :fields="fields"
                :prefix="prefix"
                :state-key="stateKey"
                :errors="errors"
                :readonly="disabled"
            />
        </div>
    </div>
</template>
