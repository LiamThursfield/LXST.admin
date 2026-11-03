<script setup lang="ts">
import { DialogTitle } from 'reka-ui';
import ContentEditor from './ContentEditor.vue';
import {
    insideContentPanelKey,
    levelContents,
    useContentPanel,
} from './contentPanel';
import type { PanelLevel } from './contentPanel';
import { textValues } from './contentSummary';
import ComponentContentField from './fields/ComponentContentField.vue';
import { nestedTemplatesKey, useSavedComponentItems } from './nestedTemplates';

/**
 * A side panel for content nested too deeply to draw inline. Each item opened from inside it goes
 * on a stack: Back and the breadcrumb come back up, and rows can step to the previous or next row
 * of their list. Edits go straight into the form, so the panel has nothing to save.
 */
const props = withDefaults(
    defineProps<{
        /** What's being edited, e.g. the page's name, as the start of the breadcrumb. */
        title?: string;
        errors?: Record<string, string | undefined>;
        readonly?: boolean;
    }>(),
    {
        title: undefined,
        errors: () => ({}),
        readonly: false,
    },
);

provide(insideContentPanelKey, true);

const { trans } = useTrans();

const panel = useContentPanel()!;
const nestedTemplates = inject(nestedTemplatesKey, ref([]));
const savedComponents = useSavedComponentItems();

const levels = panel.levels;
const level = computed(() => levels.value.at(-1));

const contents = computed(() =>
    level.value ? levelContents(level.value, nestedTemplates.value) : null,
);

const open = computed({
    get: () => levels.value.length > 0,
    set: (value: boolean) => {
        if (!value) {
            panel.closeTo(0);
        }
    },
});

function levelTitle(item: PanelLevel): string {
    const itemContents = levelContents(item, nestedTemplates.value);

    if (item.index === null) {
        return item.field.name;
    }

    return (
        textValues(
            itemContents?.fields ?? [],
            itemContents?.values,
            nestedTemplates.value,
            savedComponents.value,
        )[0] ??
        trans('cms.content.repeater.item', { number: item.index.value + 1 })
    );
}

/** The path to the open item, each part with how many levels stay open when it's chosen. */
const crumbs = computed(() => [
    ...(props.title ? [{ label: props.title, keep: 0 }] : []),
    ...levels.value.flatMap((item, index) => [
        ...(item.index === null
            ? []
            : [{ label: item.field.name, keep: index }]),
        { label: levelTitle(item), keep: index + 1 },
    ]),
]);

const heading = computed(() => (level.value ? levelTitle(level.value) : ''));

const backLabel = computed(() => {
    const previous = crumbs.value.at(-2);

    return previous
        ? trans('cms.content.panel.back_to', { name: previous.label })
        : trans('cms.content.panel.back');
});

const rowCount = computed<number>(() =>
    level.value ? (level.value.parent[level.value.field.slug]?.length ?? 0) : 0,
);

const rowIndex = computed(() => level.value?.index?.value ?? null);

const isReadonly = computed(() => props.readonly || !!level.value?.readonly);

function back() {
    panel.closeTo(levels.value.length - 1);
}

function step(offset: number) {
    if (level.value?.index) {
        level.value.index.value += offset;
    }
}

function removeItem() {
    const item = level.value;

    if (item?.index) {
        const index = item.index.value;

        back();
        item.parent[item.field.slug] = item.parent[item.field.slug].filter(
            (_: unknown, rowNumber: number) => rowNumber !== index,
        );
    }
}

const root = useTemplateRef<HTMLElement>('root');

// Start each item at its first error if it has one, or else at the top
watch(
    [root, () => contents.value?.stateKey],
    () => {
        const target =
            root.value?.querySelector(
                '[data-content-error], [aria-invalid="true"]',
            ) ?? root.value;

        target?.scrollIntoView({ block: 'center' });
    },
    { flush: 'post' },
);
</script>

<template>
    <USlideover
        v-model:open="open"
        :title="heading"
        :ui="{
            content: 'sm:max-w-xl',
            header: 'flex-col items-stretch gap-1.5',
        }"
    >
        <template #header>
            <nav
                :aria-label="trans('cms.content.panel.breadcrumb')"
                class="text-muted flex flex-wrap items-center gap-1.5 text-sm"
            >
                <template
                    v-for="(crumb, index) in crumbs"
                    :key="`${index}-${crumb.keep}`"
                >
                    <button
                        v-if="index < crumbs.length - 1"
                        type="button"
                        class="hover:text-default focus-visible:outline-primary rounded focus-visible:outline-2"
                        @click="panel.closeTo(crumb.keep)"
                    >
                        {{ crumb.label }}
                    </button>
                    <span v-else class="text-highlighted" aria-current="true">
                        {{ crumb.label }}
                    </span>
                    <UIcon
                        v-if="index < crumbs.length - 1"
                        name="i-lucide-chevron-right"
                        class="size-3 shrink-0"
                    />
                </template>
            </nav>

            <div class="flex items-center gap-2">
                <UButton
                    color="neutral"
                    variant="ghost"
                    icon="i-lucide-arrow-left"
                    :aria-label="backLabel"
                    @click="back"
                />
                <DialogTitle
                    as="h2"
                    class="text-highlighted min-w-0 flex-1 truncate font-semibold"
                >
                    {{ heading }}
                </DialogTitle>
                <template v-if="rowIndex !== null">
                    <UButton
                        color="neutral"
                        variant="ghost"
                        icon="i-lucide-chevron-up"
                        :disabled="rowIndex === 0"
                        :aria-label="trans('cms.content.panel.previous')"
                        @click="step(-1)"
                    />
                    <UButton
                        color="neutral"
                        variant="ghost"
                        icon="i-lucide-chevron-down"
                        :disabled="rowIndex >= rowCount - 1"
                        :aria-label="trans('cms.content.panel.next')"
                        @click="step(1)"
                    />
                </template>
                <UButton
                    color="neutral"
                    variant="ghost"
                    icon="i-lucide-x"
                    :aria-label="trans('cms.content.panel.close')"
                    @click="panel.closeTo(0)"
                />
            </div>
        </template>

        <template #body>
            <div ref="root">
                <ComponentContentField
                    v-if="contents?.component"
                    :key="contents.stateKey"
                    v-model="
                        contents.component.parent[contents.component.field.slug]
                    "
                    :field="contents.component.field"
                    :parent-values="contents.component.parent"
                    :prefix="contents.prefix"
                    :state-key="contents.stateKey"
                    :errors="errors"
                    :disabled="isReadonly"
                    :framed="false"
                    is-top-level
                />
                <ContentEditor
                    v-else-if="contents"
                    :key="contents.stateKey"
                    :model-value="contents.values"
                    :fields="contents.fields"
                    :sections="contents.sections"
                    :prefix="contents.prefix"
                    :state-key="contents.stateKey"
                    :errors="errors"
                    :readonly="isReadonly"
                />
            </div>
        </template>

        <template #footer>
            <div class="flex w-full items-center justify-between gap-2">
                <UButton
                    v-if="rowIndex !== null && !isReadonly"
                    color="error"
                    variant="ghost"
                    :label="trans('cms.content.panel.remove')"
                    @click="removeItem"
                />
                <UButton
                    class="ms-auto"
                    color="neutral"
                    variant="subtle"
                    :label="trans('cms.content.panel.done')"
                    @click="panel.closeTo(0)"
                />
            </div>
        </template>
    </USlideover>
</template>
